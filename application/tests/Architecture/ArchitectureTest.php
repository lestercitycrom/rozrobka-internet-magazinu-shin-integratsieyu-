<?php

arch('debugging helpers are not used in application code')
    ->expect(['dd', 'dump', 'ray'])
    ->not->toBeUsed();

arch('application code does not depend on test code')
    ->expect('App')
    ->not->toUse('Tests');
