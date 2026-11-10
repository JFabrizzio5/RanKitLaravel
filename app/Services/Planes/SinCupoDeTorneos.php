<?php

namespace App\Services\Planes;

use RuntimeException;

/** El organizador no tiene plan vigente o ya usó los torneos de su plan. */
class SinCupoDeTorneos extends RuntimeException
{
}
