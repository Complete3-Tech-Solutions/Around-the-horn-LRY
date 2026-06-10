<?php

namespace App\Command;

use App\Entity\Poll;
use App\Event\EventConfig;
use App\Repository\PollRepository;
use App\Service\User\UserService;
use Doctrine\ORM\EntityManagerInterface;
use Symfony\Component\Console\Attribute\AsCommand;
use Symfony\Component\Console\Command\Command;
use Symfony\Component\Console\Input\InputArgument;
use Symfony\Component\Console\Input\InputInterface;
use Symfony\Component\Console\Input\InputOption;
use Symfony\Component\Console\Output\OutputInterface;
use Symfony\Component\Console\Style\SymfonyStyle;

/**
 * One-shot event setup: seeds the four "Around the Horn" round polls (each with
 * the three founders as fixed options, tagged with their round number, left as
 * drafts for the moderator to activate) and optionally creates an admin user.
 *
 * Idempotent: existing rounds are skipped unless --reset is passed.
 *
 *   php bin/console app:event:setup                       # seed rounds only
 *   php bin/console app:event:setup moderator 'S3cret!'   # seed + admin user
 *   php bin/console app:event:setup --reset               # wipe & reseed rounds
 */
#[AsCommand(
    name: 'app:event:setup',
    description: 'Seed the four Around the Horn rounds and optionally create an admin user',
)]
class EventSetupCommand extends Command
{
    public function __construct(
        private readonly EntityManagerInterface $entityManager,
        private readonly PollRepository $pollRepository,
        private readonly EventConfig $eventConfig,
        private readonly UserService $userService,
    ) {
        parent::__construct();
    }

    protected function configure(): void
    {
        $this
            ->addArgument('admin-username', InputArgument::OPTIONAL, 'Admin username to create')
            ->addArgument('admin-password', InputArgument::OPTIONAL, 'Admin password')
            ->addOption('reset', null, InputOption::VALUE_NONE, 'Delete existing round polls and recreate them')
        ;
    }

    protected function execute(InputInterface $input, OutputInterface $output): int
    {
        $io = new SymfonyStyle($input, $output);

        $existing = [];
        foreach ($this->pollRepository->findRoundPolls() as $poll) {
            $existing[$poll->getRoundNumber()] = $poll;
        }

        if ($input->getOption('reset') && [] !== $existing) {
            foreach ($existing as $poll) {
                $this->entityManager->remove($poll);
            }
            $this->entityManager->flush();
            $existing = [];
            $io->warning('Existing round polls removed (--reset).');
        }

        $founders = $this->eventConfig->founders();
        $utc = new \DateTimeZone('UTC');
        $created = 0;

        foreach ($this->eventConfig->rounds() as $round) {
            if (isset($existing[$round['number']])) {
                $io->writeln(sprintf(' • Round %d already exists — skipping.', $round['number']));
                continue;
            }

            $poll = new Poll();
            $poll
                ->setTitle($round['title'])
                ->setShortCode('round'.$round['number'])
                ->setRoundNumber($round['number'])
                ->setStartAt(new \DateTimeImmutable('now', $utc))
                ->setEndAt(new \DateTimeImmutable('+60 minutes', $utc))
                ->setDraft(true);

            // Founders in fixed ballot order => poll choices 1..3.
            foreach ($founders as $i => $founder) {
                $setter = 'setQuestion'.($i + 1);
                $poll->{$setter}($founder['name']);
            }

            $this->entityManager->persist($poll);
            ++$created;
            $io->writeln(sprintf(' • Round %d "%s" created (vote URL: /poll/round%d).', $round['number'], $round['label'], $round['number']));
        }

        $this->entityManager->flush();
        $io->success(sprintf('%d round(s) created. The 4 rounds are drafts — activate them from /admin.', $created));

        $username = $input->getArgument('admin-username');
        $password = $input->getArgument('admin-password');
        if ($username && $password) {
            try {
                $this->userService->createUser($username, $password);
                $io->success(sprintf('Admin user "%s" created.', $username));
            } catch (\Exception $e) {
                $io->warning('Admin user not created: '.$e->getMessage());
            }
        } else {
            $io->note('No admin credentials passed. Create one with: php bin/console app:create-user <user> <pass>');
        }

        return Command::SUCCESS;
    }
}
