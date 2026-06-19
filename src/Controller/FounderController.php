<?php

namespace App\Controller;

use App\Entity\Founder;
use App\Enum\FlashTypeEnum;
use App\Form\FounderType;
use App\Repository\FounderRepository;
use App\Service\Founder\FounderService;
use Doctrine\ORM\EntityManagerInterface;
use Symfony\Bundle\FrameworkBundle\Controller\AbstractController;
use Symfony\Component\HttpFoundation\File\UploadedFile;
use Symfony\Component\HttpFoundation\Request;
use Symfony\Component\HttpFoundation\Response;
use Symfony\Component\Routing\Attribute\Route;

/**
 * Founder roster management. The admin CRUD lives under /admin (firewall:
 * ROLE_USER); the headshot image is served by a PUBLIC, cacheable route so the
 * audience phones and the OBS wall can render it without auth and without
 * re-downloading it on every poll. Roster changes re-sync the round ballots.
 */
class FounderController extends AbstractController
{
    public function __construct(
        private readonly FounderRepository $founderRepository,
        private readonly FounderService $founderService,
        private readonly EntityManagerInterface $entityManager,
    ) {
    }

    #[Route('/admin/founders', name: 'app_admin_founders', methods: ['GET'])]
    public function index(): Response
    {
        // Make sure the editable roster exists even if the event was seeded
        // before founders moved into the DB.
        $this->founderService->seedDefaults();

        return $this->render('admin/founders.html.twig', [
            'founders' => $this->founderRepository->findOrdered(),
            'canAdd' => $this->founderService->canAdd(),
            'max' => FounderService::MAX_FOUNDERS,
        ]);
    }

    #[Route('/admin/founders/new', name: 'app_admin_founder_new', methods: ['POST'])]
    public function new(Request $request): Response
    {
        if (!$this->isCsrfTokenValid('founder_new', (string) $request->request->get('_token'))) {
            $this->addFlash(FlashTypeEnum::ERROR->value, 'Invalid CSRF token.');

            return $this->redirectToRoute('app_admin_founders');
        }

        if (!$this->founderService->canAdd()) {
            $this->addFlash(FlashTypeEnum::ERROR->value, sprintf('The ballot supports at most %d founders.', FounderService::MAX_FOUNDERS));

            return $this->redirectToRoute('app_admin_founders');
        }

        $founder = (new Founder())
            ->setPosition($this->founderService->nextPosition())
            ->setName('New founder');
        $this->entityManager->persist($founder);
        $this->entityManager->flush();
        $this->founderService->syncRoundBallots();

        return $this->redirectToRoute('app_admin_founder_edit', ['id' => $founder->getId()]);
    }

    #[Route('/admin/founders/{id}/edit', name: 'app_admin_founder_edit', methods: ['GET', 'POST'])]
    public function edit(Request $request, Founder $founder): Response
    {
        $form = $this->createForm(FounderType::class, $founder);
        $form->handleRequest($request);

        if ($form->isSubmitted() && $form->isValid()) {
            /** @var UploadedFile|null $file */
            $file = $form->get('headshotFile')->getData();
            if (null !== $file) {
                $founder
                    ->setHeadshotData(base64_encode((string) file_get_contents($file->getPathname())))
                    ->setHeadshotMime($file->getMimeType() ?: 'application/octet-stream');
            }
            $founder->bumpVersion();

            $this->entityManager->flush();
            // Name may have changed → keep the round ballots in step.
            $this->founderService->syncRoundBallots();

            $this->addFlash(FlashTypeEnum::SUCCESS->value, sprintf('Founder "%s" saved.', $founder->getName()));

            return $this->redirectToRoute('app_admin_founders');
        }

        return $this->render('admin/founder_edit.html.twig', [
            'founder' => $founder,
            'form' => $form,
        ]);
    }

    #[Route('/admin/founders/{id}/delete', name: 'app_admin_founder_delete', methods: ['POST'])]
    public function delete(Request $request, Founder $founder): Response
    {
        if (!$this->isCsrfTokenValid('founder_delete'.$founder->getId(), (string) $request->request->get('_token'))) {
            $this->addFlash(FlashTypeEnum::ERROR->value, 'Invalid CSRF token.');

            return $this->redirectToRoute('app_admin_founders');
        }

        $name = $founder->getName();
        $this->entityManager->remove($founder);
        $this->entityManager->flush();

        // Close position gaps, then re-sync ballots to the new roster.
        $this->founderService->repackPositions();
        $this->founderService->syncRoundBallots();

        $this->addFlash(FlashTypeEnum::SUCCESS->value, sprintf('Founder "%s" removed.', $name));

        return $this->redirectToRoute('app_admin_founders');
    }

    /**
     * Public, cacheable headshot. The ?v= version query busts the cache when a
     * new image is uploaded, so we can cache aggressively (immutable).
     */
    #[Route('/founders/{id}/photo', name: 'app_founder_photo', methods: ['GET'])]
    public function photo(Founder $founder): Response
    {
        if (!$founder->hasHeadshot()) {
            throw $this->createNotFoundException('No headshot for this founder.');
        }

        $bytes = base64_decode((string) $founder->getHeadshotData(), true);
        if (false === $bytes) {
            throw $this->createNotFoundException('Corrupt headshot.');
        }

        $response = new Response($bytes);
        $response->headers->set('Content-Type', (string) $founder->getHeadshotMime());
        $response->setPublic();
        $response->setMaxAge(31536000);
        $response->headers->addCacheControlDirective('immutable');
        $response->setEtag(sprintf('f%d-v%d', $founder->getId(), $founder->getVersion()));

        return $response;
    }
}
