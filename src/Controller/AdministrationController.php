<?php

declare(strict_types=1);

namespace App\Controller;

use App\Form\InvitationType;
use App\Invitation\InvitationAdministrator;
use App\Invitation\InvitationLink;
use App\Invitation\InvitationOptions;
use App\Invitation\InvitationReader;
use App\Invitation\IssuedInvitation;
use App\Invitation\OperationIntents;
use Symfony\Bundle\FrameworkBundle\Controller\AbstractController;
use Symfony\Component\Form\FormError;
use Symfony\Component\HttpFoundation\Request;
use Symfony\Component\HttpFoundation\Response;
use Symfony\Component\Routing\Attribute\Route;

final class AdministrationController extends AbstractController
{
    #[Route('/invitations/new', name: 'invitation_create', methods: ['GET', 'POST'])]
    public function create(Request $request, OperationIntents $intents, InvitationAdministrator $administrator, InvitationLink $links): Response
    {
        $options = new InvitationOptions();
        if ($request->isMethod('GET')) { $options->operationId = $intents->issue('create'); }
        $form = $this->createForm(InvitationType::class, $options);
        $form->handleRequest($request);
        $status = 200;
        if ($form->isSubmitted()) {
            $status = 422;
            if ($form->isValid()) {
                if (!$intents->allows($options->operationId, 'create', $options->operationId)) {
                    return $this->receipt('This creation form expired. Inspect the list before creating a new invitation.', 409);
                }
                $issued = IssuedInvitation::generate($options->operationId);
                try {
                    // Validate the link before committing an otherwise unrecoverable secret.
                    $link = $links->for($issued->credential);
                    $created = $administrator->create($issued, $options, $this->getUser()->getUserIdentifier());
                } catch (\RuntimeException|\LogicException $error) {
                    $form->addError(new FormError($error->getMessage()));
                    return $this->render('invitation/form.html.twig', ['form' => $form, 'editing' => false, 'invitation' => null], new Response(status: 503));
                }
                if (!$created) { return $this->receipt('This invitation was already issued. Its code cannot be recovered. Inspect it before revoking and creating a replacement.', 409, $issued->id); }
                return $this->render('invitation/issued.html.twig', ['issued' => $issued, 'link' => $link], new Response(status: 201));
            }
        }
        return $this->render('invitation/form.html.twig', ['form' => $form, 'editing' => false, 'invitation' => null], new Response(status: $status));
    }

    #[Route('/invitations/{id}/edit', name: 'invitation_edit', requirements: ['id' => '[a-f0-9]{32}'], methods: ['GET', 'POST'])]
    public function edit(string $id, Request $request, InvitationReader $reader, OperationIntents $intents, InvitationAdministrator $administrator): Response
    {
        try { $invitation = $reader->find($id); }
        catch (\RuntimeException $error) { return $this->receipt($error->getMessage(), 503); }
        if ($invitation === null) { throw $this->createNotFoundException(); }
        if ($invitation['revoked_at'] !== null) { return $this->receipt('Revoked invitations cannot be edited or reactivated.', 409, $id); }
        $options = new InvitationOptions();
        $options->name = $invitation['name'];
        $options->maxRequests = (string) $invitation['max_requests'];
        $options->budgetUsd = substr(str_pad((string) $invitation['budget_micro_usd'], 7, '0', STR_PAD_LEFT), 0, -6).'.'.str_pad((string) ($invitation['budget_micro_usd'] % 1000000), 6, '0', STR_PAD_LEFT);
        $options->requestsPerMinute = (string) $invitation['requests_per_minute'];
        $options->expiresInDays = '';
        $options->version = $invitation['metadata_version'];
        if ($request->isMethod('GET')) { $options->operationId = $intents->issue('update', $id); }
        $form = $this->createForm(InvitationType::class, $options, ['editing' => true]);
        $form->handleRequest($request);
        $status = $form->isSubmitted() ? 422 : 200;
        if ($form->isSubmitted() && $form->isValid()) {
            if (!$intents->allows($options->operationId, 'update', $id)) { return $this->receipt('This edit form expired. Reload current invitation details.', 409, $id); }
            try { $result = $administrator->update($id, $options, $this->getUser()->getUserIdentifier()); }
            catch (\RuntimeException $error) { $form->addError(new FormError($error->getMessage())); return $this->render('invitation/form.html.twig', ['form' => $form, 'editing' => true, 'invitation' => $invitation], new Response(status: 503)); }
            if (in_array($result, ['updated', 'replayed'], true)) { return $this->redirectToRoute('invitation_detail', ['id' => $id], Response::HTTP_SEE_OTHER); }
            return $this->receipt('The invitation changed or is no longer editable. Reload current details before trying again.', 409, $id);
        }
        return $this->render('invitation/form.html.twig', ['form' => $form, 'editing' => true, 'invitation' => $invitation], new Response(status: $status));
    }

    #[Route('/invitations/{id}/revoke', name: 'invitation_revoke', requirements: ['id' => '[a-f0-9]{32}'], methods: ['POST'])]
    public function revoke(string $id, Request $request, OperationIntents $intents, InvitationAdministrator $administrator): Response
    {
        if (!$this->isCsrfTokenValid('revoke-'.$id, $request->request->get('_token'))) { throw $this->createAccessDeniedException('Invalid CSRF token.'); }
        $operation = $request->request->get('operation', '');
        if (!$intents->allows($operation, 'revoke', $id)) { return $this->receipt('This revocation form expired. Reload current invitation details.', 409, $id); }
        try { $result = $administrator->revoke($operation, $id, $this->getUser()->getUserIdentifier()); }
        catch (\RuntimeException $error) { return $this->receipt($error->getMessage(), 503, $id); }
        if ($result === 'missing') { throw $this->createNotFoundException(); }
        return $this->redirectToRoute('invitation_detail', ['id' => $id], Response::HTTP_SEE_OTHER);
    }

    private function receipt(string $message, int $status, ?string $id = null): Response
    {
        return $this->render('invitation/receipt.html.twig', ['message' => $message, 'id' => $id], new Response(status: $status));
    }
}
