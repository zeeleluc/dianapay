<?php

namespace App\Services;

use Abraham\TwitterOAuth\TwitterOAuth;
use Abraham\TwitterOAuth\TwitterOAuthException;

class XPost
{
    private string $oauthId = '';
    private string $text = '';
    private ?string $image = null;

    private string $consumerKey;
    private string $consumerSecret;
    private string $tokenIdentifier;
    private string $tokenSecret;

    public function __construct()
    {
        $this->loadCredentialsFromConfig();
    }

    private function loadCredentialsFromConfig(): void
    {
        $config = config('twitter');
        $default = $config['default_access'] ?? 'main';

        $account = $config['accounts'][$default] ?? [];

        $this->consumerKey     = $account['consumer_key'];
        $this->consumerSecret  = $account['consumer_secret'];
        $this->tokenIdentifier = $account['access_token'];
        $this->tokenSecret     = $account['access_token_secret'];

        // Optional: set user ID if needed for tagging media uploads
        $this->oauthId = env('TWITTER_USER_ID', '');
    }

    public function setText(string $text): self
    {
        $this->text = $text;
        return $this;
    }

    public function setImage(string $path): self
    {
        $this->image = $path;
        return $this;
    }

    public function getImage(): ?string
    {
        return $this->image;
    }

    public function clear(): self
    {
        if ($this->image && file_exists($this->image)) {
            unlink($this->image);
        }
        $this->image = null;
        return $this;
    }

    /**
     * Post a tweet.
     *
     * @throws TwitterOAuthException
     */
    public function post(): array
    {
        $params = ['text' => html_entity_decode($this->text)];

        $this->attachMedia($params);

        return $this->send('tweets', $params, '2');
    }

    /**
     * Reply to a tweet.
     *
     * @throws TwitterOAuthException
     */
    public function reply(string $tweetId): array
    {
        $params = [
            'text' => html_entity_decode($this->text),
            'reply' => ['in_reply_to_tweet_id' => $tweetId],
        ];

        $this->attachMedia($params);

        return $this->send('tweets', $params, '2');
    }

    private function attachMedia(array &$params): void
    {
        if (!$this->image) {
            return;
        }

        $upload = $this->connection('1.1')->upload('media/upload', [
            'media' => $this->image,
        ], ['chunkedUpload' => false]);

        if (!empty($upload->media_id_string)) {
            $params['media'] = [
                'tagged_user_ids' => $this->oauthId ? [$this->oauthId] : [],
                'media_ids' => [$upload->media_id_string],
            ];
        }
    }

    private function send(string $endpoint, array $params, string $version): array
    {
        $connection = $this->connection($version);
        $response = $connection->post($endpoint, $params, ['jsonPayload' => true]);

        return json_decode(json_encode($response), true);
    }

    private function connection(string $version): TwitterOAuth
    {
        $twitter = new TwitterOAuth(
            $this->consumerKey,
            $this->consumerSecret,
            $this->tokenIdentifier,
            $this->tokenSecret
        );

        $twitter->setApiVersion($version);
        $twitter->setTimeouts(30, 60);

        return $twitter;
    }
}
