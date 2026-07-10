<?php

declare(strict_types=1);

namespace DoctrineMigrations;

use Doctrine\DBAL\Schema\Schema;
use Doctrine\Migrations\AbstractMigration;

final class Version20260101000000 extends AbstractMigration
{
    public function up(Schema $schema): void
    {
        $this->addSql('CREATE TABLE users (
            id SERIAL PRIMARY KEY,
            name VARCHAR(255) NOT NULL,
            email VARCHAR(255) NOT NULL UNIQUE,
            password VARCHAR(255) NOT NULL,
            created_at TIMESTAMP DEFAULT NOW(),
            updated_at TIMESTAMP DEFAULT NOW()
        )');

        $this->addSql('CREATE TABLE posts (
            id SERIAL PRIMARY KEY,
            user_id INT NOT NULL REFERENCES users(id) ON DELETE CASCADE,
            title VARCHAR(255) NOT NULL,
            body TEXT NOT NULL,
            views_count INT NOT NULL DEFAULT 0,
            created_at TIMESTAMP DEFAULT NOW(),
            updated_at TIMESTAMP DEFAULT NOW()
        )');

        $this->addSql('CREATE INDEX idx_posts_user_id ON posts (user_id)');

        $this->addSql('CREATE TABLE post_views (
            id SERIAL PRIMARY KEY,
            post_id INT NOT NULL REFERENCES posts(id) ON DELETE CASCADE,
            ip_address VARCHAR(45) NOT NULL,
            viewed_at TIMESTAMP DEFAULT NOW()
        )');

        $this->addSql('CREATE INDEX idx_post_views_post_id ON post_views (post_id)');
    }

    public function down(Schema $schema): void
    {
        $this->addSql('DROP TABLE post_views');
        $this->addSql('DROP TABLE posts');
        $this->addSql('DROP TABLE users');
    }
}
