<?php

namespace App\Services\Authentication;

use App\Models\User;
use Laravel\Socialite\Contracts\User as SocialUser;
use Illuminate\Support\Facades\Auth;
use Illuminate\Support\Facades\Hash;
use Illuminate\Support\Str;

class SocialLoginService
{
    /**
     * Validate provider configuration
     */
    public function validateProviderConfig(string $provider): void
    {
        $clientId = config("services.{$provider}.client_id");
        $clientSecret = config("services.{$provider}.client_secret");
        
        if (!$clientId || !$clientSecret) {
            throw new \Exception("{$provider} is not configured properly");
        }
    }

    /**
     * Handle social login
     */
    public function handleLogin(string $provider, SocialUser $socialUser): array
    {
        // Check if user exists
        $user = User::where('email', $socialUser->getEmail())->first();
        
        if ($user) {
            // User exists, update social login info
            $this->updateSocialLoginRecord($user, $provider, $socialUser->getId());
            
            return [
                'success' => true,
                'user' => $user,
                'message' => 'Login successful'
            ];
        }
        
        // User doesn't exist - return info for registration
        return [
            'success' => false,
            'email' => $socialUser->getEmail(),
            'name' => $socialUser->getName(),
            'message' => 'No account found with this email. Please register first.'
        ];
    }

    /**
     * Update social login record for user
     */
    protected function updateSocialLoginRecord(User $user, string $provider, string $providerId): void
    {
        $metadata = $user->metadata ?? [];
        $metadata['social_logins'] = $metadata['social_logins'] ?? [];
        $metadata['social_logins'][$provider] = [
            'provider_id' => $providerId,
            'last_login' => now()->toISOString(),
            'avatar' => null
        ];
        
        $user->metadata = $metadata;
        $user->save();
    }

    /**
     * Link social account to existing user
     */
    public function linkAccount(User $user, string $provider, SocialUser $socialUser): void
    {
        $this->updateSocialLoginRecord($user, $provider, $socialUser->getId());
    }

    /**
     * Unlink social account
     */
    public function unlinkAccount(User $user, string $provider): bool
    {
        $metadata = $user->metadata ?? [];
        
        if (isset($metadata['social_logins'][$provider])) {
            unset($metadata['social_logins'][$provider]);
            $user->metadata = $metadata;
            $user->save();
            return true;
        }
        
        return false;
    }

    /**
     * Handle callback error
     */
    public function handleCallbackError(string $provider, \Exception $e): \Illuminate\Http\RedirectResponse
    {
        // Check if it's a specific error
        if (str_contains($e->getMessage(), 'access_denied')) {
            return redirect()->route('login')
                ->with('info', "You denied access to your {$provider} account. Please try again or use email login.");
        }
        
        if (str_contains($e->getMessage(), 'redirect_uri_mismatch')) {
            return redirect()->route('login')
                ->withErrors(['login' => "{$provider} OAuth configuration error. Please contact administrator."]);
        }
        
        // Generic error
        return redirect()->route('login')
            ->withErrors(['login' => "Unable to login with {$provider}. Error: " . $e->getMessage()]);
    }

    /**
     * Get social login URL for provider
     */
    public function getLoginUrl(string $provider): string
    {
        $this->validateProviderConfig($provider);
        
        $scopes = config("services.{$provider}.scopes", []);
        
        if (empty($scopes)) {
            return \Laravel\Socialite\Facades\Socialite::driver($provider)
                ->redirect()
                ->getTargetUrl();
        }
        
        return \Laravel\Socialite\Facades\Socialite::driver($provider)
            ->scopes($scopes)
            ->redirect()
            ->getTargetUrl();
    }
}