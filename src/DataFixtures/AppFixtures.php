<?php

namespace App\DataFixtures;

use App\Entity\Poll;
use App\Entity\Vote;
use Doctrine\Bundle\FixturesBundle\Fixture;
use Doctrine\Persistence\ObjectManager;

/**
 * Dev-only demo data.
 *
 * IMPORTANT: this fixture intentionally creates NO user accounts. The upstream
 * version seeded admin/adminpass and user/userpass, which is a credential
 * landmine for a public event. Admin accounts are created explicitly via
 * `app:create-user` or `app:event:setup`. Never run fixtures in production.
 */
class AppFixtures extends Fixture
{
    public function load(ObjectManager $manager): void
    {
        $this->loadPolls($manager);
        $manager->flush();
    }

    private function loadPolls(ObjectManager $manager): void
    {
        $poll = new Poll();
        $poll
            ->setTitle('Demo Poll (dev only)')
            ->setShortCode('demo01')
            ->setStartAt(new \DateTimeImmutable('now'))
            ->setEndAt(new \DateTimeImmutable('+1 hour'))
            ->setQuestion1('Choice 1')
            ->setQuestion2('Choice 2')
            ->setQuestion3('Choice 3');
        $manager->persist($poll);

        for ($i = 1; $i <= 10; ++$i) {
            $vote = new Vote();
            $vote->setPoll($poll);
            $vote->setChoice(($i % 3) + 1);
            $vote->setVoterId('demo_'.$i);
            $vote->setCreatedAt(new \DateTimeImmutable());
            $manager->persist($vote);
        }
    }
}
