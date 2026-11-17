<?php

namespace Blackbox\FilamentBlackbox\Exceptions;

use OwenIt\Auditing\Exceptions\AuditableTransitionException;

/**
 * A Revert/Recover was refused because applying the audit would dirty an
 * attribute the auditable model's CURRENT owen-it rules exclude (a setter on
 * an allowed field touched an excluded one). The model was reset to its
 * original state; nothing was shown or saved.
 *
 * It extends owen-it's AuditableTransitionException, so callers that already
 * handle refused transitions handle this one the same way.
 */
class ProtectedAttributeTransitionException extends AuditableTransitionException {}
