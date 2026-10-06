<?php

it('sends guests from the home page to the login', function () {
    $this->get('/')->assertRedirect(route('login'));
});
