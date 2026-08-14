<?php

namespace App\Exceptions;

use LogicException;

class IdempotencyConflictException extends LogicException {}
