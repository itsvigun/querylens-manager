"""Credential-safe HTTP acceptance for the Apache image or the deployed service.

No chat/provider request, credential-bearing URL, response dump or secret file is
created. --hosted uses the private owner record from the explicit Railway job.
"""

import argparse
import http.client
import json
import re
import subprocess
import time
from html.parser import HTMLParser
from pathlib import Path
from urllib.parse import urlencode, urlsplit

ROOT = Path(__file__).resolve().parents[1]
PROJECT = 'b5a42a05-9ecc-4fb3-8564-87d44ddeeb6f'
MANAGER = 'https://querylens-manager-production.up.railway.app'
PYTHON = 'https://querylens-production-f241.up.railway.app'
STAGE = 'configuration'


class Forms(HTMLParser):
    def __init__(self, source):
        super().__init__()
        self.forms = []
        self.current = None
        self.feed(source)

    def handle_starttag(self, tag, attributes):
        values = dict(attributes)
        if tag == 'form':
            self.current = {'action': values.get('action', ''), 'inputs': {}}
            self.forms.append(self.current)
        elif tag == 'input' and self.current is not None and 'name' in values:
            self.current['inputs'][values['name']] = values.get('value', '')

    def handle_endtag(self, tag):
        if tag == 'form':
            self.current = None

    def containing(self, name):
        return next(form['inputs'].copy() for form in self.forms if name in form['inputs'])


class Client:
    def __init__(self, origin, transport=None):
        self.origin = origin
        self.transport = urlsplit(transport or origin)
        self.cookies = {}

    def request(self, method, path, data=None, json_body=False, headers=None):
        cls = http.client.HTTPSConnection if self.transport.scheme == 'https' else http.client.HTTPConnection
        connection = cls(self.transport.hostname, self.transport.port, timeout=15)
        selected = {'Host': urlsplit(self.origin).netloc, 'Origin': self.origin}
        if self.cookies:
            selected['Cookie'] = '; '.join(key + '=' + value for key, value in self.cookies.items())
        if headers:
            selected.update(headers)
        body = None
        if data is not None:
            selected['Content-Type'] = 'application/json' if json_body else 'application/x-www-form-urlencoded'
            body = json.dumps(data) if json_body else urlencode(data)
        connection.request(method, path, body=body, headers=selected)
        response = connection.getresponse()
        content = response.read().decode()
        result = response.status, response.getheaders(), content
        for name, value in result[1]:
            if name.lower() == 'set-cookie':
                pair = value.split(';', 1)[0]
                key, value = pair.split('=', 1)
                self.cookies[key] = value
        connection.close()
        return result


def header(headers, name):
    return next((value for key, value in headers if key.lower() == name.lower()), '')


def wait_live(client):
    for _ in range(30):
        try:
            status, _, body = client.request('GET', '/health/live')
            if status == 200 and json.loads(body) == {'status': 'ok'}:
                return
        except Exception:
            pass
        time.sleep(2)
    raise RuntimeError('Liveness did not return in time.')


def hosted_boot_tick():
    probe = subprocess.run(['railway', 'ssh', '--project', PROJECT, '--environment', 'production', '--service', 'querylens-manager', '--', 'php', '-r', 'echo "BOOT_TICK=",explode(" ",file_get_contents("/proc/1/stat"))[21],PHP_EOL;'], stdout=subprocess.PIPE, stderr=subprocess.PIPE, timeout=25)
    match = re.search(rb'BOOT_TICK=([0-9]+)', probe.stdout)
    if probe.returncode or match is None:
        raise RuntimeError('Manager process identity check failed.')
    return match[1]


def main(hosted):
    global STAGE
    if hosted:
        settings = json.loads((ROOT / '.access-tokens/railway-manager-secrets.json').read_text())
        password = settings['owner_password']
        manager = Client(MANAGER)
    else:
        password = 'offline-production-smoke-password'
        manager = Client('https://manager.test', 'http://127.0.0.1:8087')
    wait_live(manager)

    STAGE = 'secure owner login'
    status, headers, html = manager.request('GET', '/login')
    assert status == 200
    flags = header(headers, 'Set-Cookie').lower()
    assert 'secure' in flags and 'httponly' in flags and 'samesite=strict' in flags
    assert 'no-store' in header(headers, 'Cache-Control')
    assert header(headers, 'Referrer-Policy') == 'no-referrer'
    assert header(headers, 'Strict-Transport-Security')
    inputs = Forms(html).containing('_username')
    inputs.update({'_username': 'owner', '_password': password})
    status, _, _ = manager.request('POST', '/login', inputs)
    assert status == 302
    status, _, html = manager.request('GET', '/')
    assert status == 200 and 'Invitations' in html

    STAGE = 'manager restart request'
    before_restart = hosted_boot_tick() if hosted else None
    if hosted:
        latest = subprocess.run(['railway', 'deployment', 'list', '--project', PROJECT, '--environment', 'production', '--service', 'querylens-manager', '--limit', '1', '--json'], stdout=subprocess.PIPE, stderr=subprocess.PIPE, timeout=30)
        deployment = json.loads(latest.stdout)[0]
        assert deployment['status'] == 'SUCCESS' and re.fullmatch(r'[a-f0-9-]{36}', deployment['id'])
        command = ['railway', 'api', 'mutation { deploymentRestart(id:"' + deployment['id'] + '") }', '--compact']
    else:
        command = ['docker', 'restart', 'querylens-manager-production-smoke-v2']
    restarted = subprocess.run(command, stdout=subprocess.PIPE, stderr=subprocess.PIPE, timeout=120)
    assert restarted.returncode == 0
    STAGE = 'persistent owner session after completed restart'
    if hosted:
        for _ in range(10):
            try:
                if hosted_boot_tick() != before_restart:
                    break
            except Exception:
                pass
            time.sleep(2)
        else:
            raise RuntimeError('Manager restart was not observed.')
    wait_live(manager)
    status, _, html = manager.request('GET', '/')
    assert status == 200 and 'Invitations' in html

    invitation = None
    try:
        if hosted:
            STAGE = 'one-time PHP issuance'
            status, _, html = manager.request('GET', '/invitations/new')
            assert status == 200
            options = Forms(html).containing('invitation[operationId]')
            options['invitation[name]'] = 'Railway integration verification'
            status, headers, html = manager.request('POST', '/invitations/new', options)
            assert status == 201
            code = re.search(r'qlt_[a-f0-9]{32}_[A-Za-z0-9_-]{43}', html).group(0)
            invitation = code.split('_', 2)[1]
            assert PYTHON + '/#at=' + code in html and 'no-store' in header(headers, 'Cache-Control')
            status, _, replay = manager.request('POST', '/invitations/new', options)
            assert status == 409 and code not in replay

            STAGE = 'hosted Python login and visitor session'
            visitor = Client(PYTHON)
            browser_headers = {'X-QueryLens-Client': 'browser', 'Sec-Fetch-Site': 'same-origin'}
            status, headers, response = visitor.request('POST', '/api/access/login', {'token': code}, True, browser_headers)
            assert status == 200
            csrf = json.loads(response)['csrf_token']
            visitor_flags = header(headers, 'Set-Cookie').lower()
            assert 'secure' in visitor_flags and 'httponly' in visitor_flags and 'samesite=strict' in visitor_flags
            status, _, response = visitor.request('GET', '/api/access/status')
            visitor_status = json.loads(response)
            assert status == 200 and visitor_status['authenticated'] is True and visitor_status['remaining_requests'] == 10
            status, _, html = visitor.request('GET', '/')
            assert status == 200 and 'QueryLens' in html
            status, _, _ = visitor.request('POST', '/api/access/logout', {}, True, {**browser_headers, 'X-CSRF-Token': csrf})
            assert status == 200

            STAGE = 'sanitized metadata and audited revocation'
            status, _, html = manager.request('GET', '/invitations/' + invitation)
            assert status == 200 and code not in html
            revoke = Forms(html).containing('operation')
            status, _, _ = manager.request('POST', '/invitations/' + invitation + '/revoke', revoke)
            assert status == 303
            status, _, html = manager.request('GET', '/invitations/' + invitation)
            assert status == 200 and 'Revoke invitation permanently' not in html
            invitation = None
        STAGE = 'owner logout'
        status, _, html = manager.request('GET', '/')
        logout = Forms(html).containing('_csrf_token')
        status, _, _ = manager.request('POST', '/logout', logout)
        assert status == 302
        status, _, _ = manager.request('GET', '/')
        assert status == 302
    finally:
        if invitation is not None:
            try:
                status, _, html = manager.request('GET', '/invitations/' + invitation)
                revoke = Forms(html).containing('operation')
                manager.request('POST', '/invitations/' + invitation + '/revoke', revoke)
            except Exception:
                print('Inspect/revoke the invitation labeled Railway integration verification.')
    print('Hosted HTTPS/login/session/restart/one-time issuance/Python login/logout/revocation passed; no AI call.' if hosted else 'Production Apache login, Secure/HttpOnly cookies, headers and persistent owner session across restart passed.')


if __name__ == '__main__':
    parser = argparse.ArgumentParser()
    parser.add_argument('--hosted', action='store_true')
    try:
        main(parser.parse_args().hosted)
    except Exception:
        raise SystemExit('Production HTTP check failed at ' + STAGE + '; credentials and response bodies omitted.') from None
