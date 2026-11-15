<?php

use App\Models\User;
use Laravel\Socialite\Facades\Socialite;
use Laravel\Socialite\Two\User as SingleSignOnUser;

beforeEach(function () {
    config(['sso.admins_only' => false, 'sso.allow_students' => true, 'sso.autocreate_new_users' => false]);
});

test('someone with an account can sign in by single sign-on', function () {
    $user = User::factory()->create(['username' => 'jmu1x', 'email' => 'jenny.mcuser@example.test']);
    Socialite::fake('keycloak', SingleSignOnUser::fake(['email' => 'jenny.mcuser@example.test', 'nickname' => 'jmu1x']));

    $this->get(route('sso.callback'))->assertRedirect(route('home'));

    $this->assertAuthenticatedAs($user);
});

test('someone with an account can sign in with a local password when single sign-on is off', function () {
    $user = User::factory()->create(['username' => 'jmu1x', 'password' => 'secret']);
    config(['sso.enabled' => false]);

    $this->post(route('login.local'), ['username' => 'jmu1x', 'password' => 'secret'])->assertRedirect(route('home'));

    $this->assertAuthenticatedAs($user);
});

test('someone who has gone cannot sign in, by single sign-on or with a local password', function () {
    User::factory()->create(['username' => 'jml1x', 'email' => 'jenny.mcleaver@example.test', 'password' => 'secret', 'left_on' => '2026-01-01']);
    Socialite::fake('keycloak', SingleSignOnUser::fake(['email' => 'jenny.mcleaver@example.test', 'nickname' => 'jml1x']));

    $this->get(route('sso.callback'))->assertForbidden();
    $this->assertGuest();

    config(['sso.enabled' => false]);
    $this->post(route('login.local'), ['username' => 'jml1x', 'password' => 'secret'])->assertSessionHasErrors('username');
    $this->assertGuest();
});
