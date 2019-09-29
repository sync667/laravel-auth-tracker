<?php

namespace AnthonyLajusticia\AuthTracker\Listeners;

use AnthonyLajusticia\AuthTracker\Factories\LoginFactory;
use AnthonyLajusticia\AuthTracker\RequestContext;
use App\Notifications\LoggedIn;
use Laravel\Passport\Events\AccessTokenCreated;
use Laravel\Passport\Token;

class ApiAuthEventSubscriber
{
    public function handleAccessTokenCreation(AccessTokenCreated $event)
    {
        // Get the created access token
        $accessToken = Token::find($event->tokenId);

        // Get the authenticated user
        $provider = config('auth.guards.api.provider');
        $userModel = config('auth.providers.'.$provider.'.model');
        $user = call_user_func([$userModel, 'find'], $accessToken->user_id);

        // Get as much information as possible about the request
        $context = new RequestContext;

        // Build a new login
        $login = LoginFactory::build($event, $context);

        // Set the expiration date
        $login->expiresAt($accessToken->expires_at);

        // Attach the login to the user and save it
        $user->logins()->save($login);

        // Notify the user by email that a login has just been made
        // (just the initial logins, not when refresh token)
        if (config('auth_tracker.notify') &&
            request()->input('grant_type') !== 'refresh_token') {

            $user->notify(new LoggedIn($context));
        }
    }

    /**
     * Register the listeners for the subscriber.
     *
     * @param  \Illuminate\Events\Dispatcher  $events
     */
    public function subscribe($events)
    {
        $events->listen(
            'Laravel\Passport\Events\AccessTokenCreated',
            'AnthonyLajusticia\AuthTracker\Listeners\ApiAuthEventSubscriber@handleAccessTokenCreation'
        );
    }
}