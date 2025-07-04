<?php

namespace App\Console\Commands;

use App\Helpers\SlackNotifier;
use App\Services\XPost;
use Illuminate\Console\Command;

class PostTweet extends Command
{
    // Add optional {image?} parameter
    protected $signature = 'tweet:post {message} {image?}';
    protected $description = 'Post a tweet to Twitter using Abraham/TwitterOAuth';

    public function handle(): void
    {
        $message = $this->argument('message');
        $image = $this->argument('image');

        $xPost = new XPost();
        $xPost->setText($message);

        if ($image) {
            $fullImagePath = public_path($image);

            if (!is_readable($fullImagePath)) {
                $this->error("Image not readable: {$fullImagePath}");
                return;
            }

            $xPost->setImage($fullImagePath);
        }

        $response = $xPost->post();

        if (isset($response['status']) && $response['status'] >= 300) {
            $text = "*Twitter post failed*\nStatus: `{$response['status']}`\nTitle: `{$response['title']}`\nDetail: `{$response['detail']}`";
            SlackNotifier::error($text);
        } else {
            SlackNotifier::success('Tweeted!');
        }
    }
}
