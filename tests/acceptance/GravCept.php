<?php
$I = new AcceptanceTester($scenario);
$I->wantTo('perform actions and see result');
$I->amOnPage('/');
$I->seeInTitle("Home | Grav");
$I->click('ul.navigation li:nth-child(6) a');
$I->seeCurrentUrlEquals('/grav/typography');
