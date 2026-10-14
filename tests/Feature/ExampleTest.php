<?php

it('serves the public landing page to guests', function () {
    $this->get('/')->assertOk()->assertSee(config('app.name'));
});
