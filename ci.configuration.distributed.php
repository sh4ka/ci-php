<?php

return function ($ci) {

    $ci[] = new \luka8088\ci\test\tool\InlineTest();

    $ci->addPath(__dir__ . '/src');
    $ci->addPath(__dir__ . '/tests');

};
