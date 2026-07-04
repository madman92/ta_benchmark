<?php

namespace Database\Seeders;

use Illuminate\Database\Seeder;
use Illuminate\Support\Facades\DB;

class BenchmarkSeeder extends Seeder
{
    public function run(): void
    {
        // Create 100 users
        $users = [];
        for ($i = 1; $i <= 100; $i++) {
            $users[] = [
                'name' => "User {$i}",
                'email' => "user{$i}@bench.test",
                'password' => bcrypt('password'),
                'created_at' => now(),
                'updated_at' => now(),
            ];
        }
        DB::table('users')->insert($users);

        // Create 1000 posts
        $posts = [];
        for ($i = 1; $i <= 1000; $i++) {
            $posts[] = [
                'user_id' => rand(1, 100),
                'title' => "Post title {$i}",
                'body' => "Body content for post {$i}. Lorem ipsum dolor sit amet.",
                'views_count' => rand(0, 500),
                'created_at' => now(),
                'updated_at' => now(),
            ];
        }

        foreach (array_chunk($posts, 100) as $chunk) {
            DB::table('posts')->insert($chunk);
        }
    }
}
