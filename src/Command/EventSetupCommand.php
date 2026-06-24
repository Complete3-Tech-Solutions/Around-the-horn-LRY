<?php

namespace App\Command;

use App\Entity\Poll;
use App\Event\EventConfig;
use App\Repository\PollRepository;
use App\Service\Founder\FounderService;
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
 * One-shot event setup: seeds the "Around the Horn" round polls (each with the
 * three founders as fixed options, tagged with their round number + metadata,
 * left as drafts for the moderator to activate) and optionally creates an admin
 * user. Rounds are editable afterwards from /admin.
 *
 * Idempotent: existing rounds are skipped unless --reset is passed. Re-running
 * after adding a round to EventConfig seeds only the missing ones (e.g. Round 5).
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
        private readonly FounderService $founderService,
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
            ->addOption('sync-metadata', null, InputOption::VALUE_NONE, 'Update round labels, questions and myths from EventConfig (keeps votes)')
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

        // Seed the editable founder roster first so the round ballots copy the
        // real names (no-op once founders exist; edit them later at /admin).
        $seededFounders = $this->founderService->seedDefaults();
        if ($seededFounders > 0) {
            $io->writeln(sprintf(' • Seeded %d founder(s) — editable at /admin.', $seededFounders));
        }

        $founders = $this->eventConfig->founders();
        $utc = new \DateTimeZone('UTC');
        $created = 0;

        $syncMeta = (bool) $input->getOption('sync-metadata');
        $synced = 0;

        foreach ($this->eventConfig->rounds() as $round) {
            if (isset($existing[$round['number']])) {
                if ($syncMeta) {
                    $poll = $existing[$round['number']];
                    $poll
                        ->setRoundLabel($round['label'])
                        ->setTitle($round['title'])
                        ->setRoundQuestion($round['question'])
                        ->setMyths($round['myths']);
                    ++$synced;
                    $io->writeln(sprintf(' • Round %d metadata synced from seed.', $round['number']));
                } else {
                    $io->writeln(sprintf(' • Round %d already exists — skipping.', $round['number']));
                }
                continue;
            }

            $poll = new Poll();
            $poll
                ->setTitle($round['title'])
                ->setShortCode('round'.$round['number'])
                ->setRoundNumber($round['number'])
                ->setRoundLabel($round['label'])
                ->setRoundQuestion($round['question'])
                ->setMyths($round['myths'])
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
        // Keep every round's ballot in lockstep with the roster (count + names).
        $this->founderService->syncRoundBallots();
        if ($synced > 0) {
            $io->success(sprintf('%d round(s) metadata synced. Edit copy any time at /admin.', $synced));
        }
        if ($created > 0) {
            $io->success(sprintf('%d round(s) created. Rounds are drafts — activate, edit, add or reset them from /admin.', $created));
        } elseif (0 === $synced) {
            $io->note('Nothing to do — all rounds exist. Pass --sync-metadata to refresh labels/questions/myths from the seed.');
        }

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
