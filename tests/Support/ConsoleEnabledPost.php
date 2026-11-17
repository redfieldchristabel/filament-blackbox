<?php

namespace Blackbox\FilamentBlackbox\Tests\Support;

/**
 * Used only by ConsoleAuditBehaviourTest, with audit.console = true set before
 * its first use. A model class boots once per process, so a class of its own
 * keeps what happens at boot independent of the order other tests run in.
 */
class ConsoleEnabledPost extends AuditedPost {}
