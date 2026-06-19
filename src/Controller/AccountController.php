<?php

namespace App\Controller;

use App\Entity\User;
use App\Enum\FlashTypeEnum;
use App\Form\ChangePasswordType;
use App\Service\User\UserService;
use Symfony\Bundle\FrameworkBundle\Controller\AbstractController;
use Symfony\Component\HttpFoundation\Request;
use Symfony\Component\HttpFoundation\Response;
use Symfony\Component\Routing\Attribute\Route;

/**
 * Moderator account self-service. Lives under /admin (firewall: ROLE_USER), so
 * only an authenticated moderator can reach it and can only change their OWN
 * password — no current-password prompt needed.
 */
class AccountController extends AbstractController
{
    public function __construct(
        private readonly UserService $userService,
    ) {
    }

    #[Route('/admin/account', name: 'app_admin_account', methods: ['GET', 'POST'])]
    public function account(Request $request): Response
    {
        $user = $this->getUser();
        if (!$user instanceof User) {
            return $this->redirectToRoute('app_admin_index');
        }

        $form = $this->createForm(ChangePasswordType::class);
        $form->handleRequest($request);

        if ($form->isSubmitted() && $form->isValid()) {
            $this->userService->changePassword($user, (string) $form->get('plainPassword')->getData());
            $this->addFlash(FlashTypeEnum::SUCCESS->value, 'Your moderator password has been updated — it takes effect immediately.');

            return $this->redirectToRoute('app_admin_index');
        }

        return $this->render('admin/account.html.twig', [
            'form' => $form,
            'username' => $user->getUserIdentifier(),
        ]);
    }
}
