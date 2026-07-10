<?php

namespace App\Command;

use Doctrine\DBAL\Connection;
use Symfony\Component\Console\Attribute\AsCommand;
use Symfony\Component\Console\Command\Command;
use Symfony\Component\Console\Input\InputInterface;
use Symfony\Component\Console\Output\OutputInterface;

#[AsCommand(name: 'app:seed', description: 'Seed benchmark data')]
class SeedCommand extends Command
{
    public function __construct(private readonly Connection $connection)
    {
        parent::__construct();
    }

    protected function execute(InputInterface $input, OutputInterface $output): int
    {
        $output->writeln('Seeding users...');
        $this->connection->executeStatement('TRUNCATE TABLE users RESTART IDENTITY CASCADE');

        for ($i = 1; $i <= 100; $i++) {
            $this->connection->executeStatement(
                'INSERT INTO users (name, email, password, created_at, updated_at) VALUES (?, ?, ?, NOW(), NOW())',
                ["User {$i}", "user{$i}@bench.test", password_hash('password', PASSWORD_BCRYPT)]
            );
        }

        $output->writeln('Seeding posts...');

        for ($i = 1; $i <= 1000; $i++) {
            $this->connection->executeStatement(
                'INSERT INTO posts (user_id, title, body, views_count, created_at, updated_at) VALUES (?, ?, ?, ?, NOW(), NOW())',
                [rand(1, 100), "Post title {$i}", "Body content for post {$i}. Lorem ipsum dolor sit amet.", rand(0, 500)]
            );
        }

        $output->writeln('Done.');

        return Command::SUCCESS;
    }
}
