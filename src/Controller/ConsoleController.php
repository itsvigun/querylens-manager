<?php

declare(strict_types=1);

namespace App\Controller;

use Symfony\Bundle\FrameworkBundle\Controller\AbstractController;
use Symfony\Component\HttpFoundation\Response;
use Symfony\Component\HttpFoundation\Request;
use App\Invitation\InvitationReader;
use App\Invitation\OperationIntents;
use Symfony\Component\Routing\Attribute\Route;

final class ConsoleController extends AbstractController
{
    #[Route('/', name: 'console', methods: ['GET'])]
    public function index(Request $request, InvitationReader $reader): Response
    {
        $page = $this->page($request);
        try { $rows = $reader->list($page); }
        catch (\RuntimeException $error) { return $this->render('unavailable.html.twig', ['message' => $error->getMessage()], new Response(status: 503)); }
        return $this->render('console.html.twig', ['invitations' => array_slice($rows, 0, InvitationReader::PAGE_SIZE), 'page' => $page, 'more' => count($rows) > InvitationReader::PAGE_SIZE]);
    }

    #[Route('/invitations/{id}', name: 'invitation_detail', requirements: ['id' => '[a-f0-9]{32}'], methods: ['GET'])]
    public function detail(string $id, Request $request, InvitationReader $reader, OperationIntents $intents): Response
    {
        $page = $this->page($request);
        try { $invitation = $reader->find($id); $rows = $invitation ? $reader->requests($id, $page) : []; }
        catch (\RuntimeException $error) { return $this->render('unavailable.html.twig', ['message' => $error->getMessage()], new Response(status: 503)); }
        if ($invitation === null) { throw $this->createNotFoundException(); }
        return $this->render('invitation/detail.html.twig', ['invitation' => $invitation, 'requests' => array_slice($rows, 0, InvitationReader::PAGE_SIZE), 'page' => $page, 'more' => count($rows) > InvitationReader::PAGE_SIZE, 'revoke_operation' => $invitation['revoked_at'] === null ? $intents->issue('revoke', $id) : null]);
    }

    private function page(Request $request): int
    {
        $page = filter_var($request->query->get('page', '1'), FILTER_VALIDATE_INT, ['options' => ['min_range' => 1, 'max_range' => 1000000]]);
        if ($page === false) { throw $this->createNotFoundException(); }
        return $page;
    }
}
