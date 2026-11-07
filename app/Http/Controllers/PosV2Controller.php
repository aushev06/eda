<?php

namespace App\Http\Controllers;

/**
 * Redesigned Material UI edition of the POS screens. Same data and actions
 * as the original POS, rendered from resources/js/pages/pos-v2.
 */
class PosV2Controller extends PosController
{
    protected string $pageDirectory = 'pos-v2';

    protected string $routePrefix = 'pos.v2.';
}
