<?php

namespace App\Services\CustomerInstallation\State;

final class StateValidator
{
    private const TRANSITIONS = [
        StateResolver::PENDING => [StateResolver::RUNNING, StateResolver::DELETED],
        StateResolver::RUNNING => [
            StateResolver::PENDING,
            StateResolver::COMPLETE,
            StateResolver::FAILED,
            StateResolver::ROLLING_BACK,
            StateResolver::INSTALLATION_READY,
            StateResolver::INSTALLATION_BLOCKED,
            StateResolver::DELETED,
        ],
        StateResolver::COMPLETE => [StateResolver::DELETED],
        StateResolver::FAILED => [
            StateResolver::RUNNING,
            StateResolver::ROLLING_BACK,
            StateResolver::DELETED,
        ],
        StateResolver::ROLLING_BACK => [
            StateResolver::ROLLED_BACK,
            StateResolver::ROLLBACK_FAILED,
        ],
        StateResolver::ROLLED_BACK => [StateResolver::RUNNING, StateResolver::DELETED],
        StateResolver::ROLLBACK_FAILED => [StateResolver::ROLLING_BACK, StateResolver::DELETED],
        StateResolver::DELETED => [],
        StateResolver::INSTALLATION_READY => [
            StateResolver::VERIFY_SUCCESS,
            StateResolver::VERIFY_FAILED,
            StateResolver::INSTALLATION_BLOCKED,
            StateResolver::FAILED,
            StateResolver::DELETED,
        ],
        StateResolver::INSTALLATION_BLOCKED => [
            StateResolver::RUNNING,
            StateResolver::FAILED,
            StateResolver::DELETED,
        ],
        StateResolver::VERIFY_SUCCESS => [
            StateResolver::CUSTOMER_READY,
            StateResolver::FAILED,
            StateResolver::DELETED,
        ],
        StateResolver::VERIFY_FAILED => [
            StateResolver::FAILED,
            StateResolver::RUNNING,
            StateResolver::DELETED,
        ],
        StateResolver::CUSTOMER_READY => [StateResolver::DELETED],
        StateResolver::INVALID_STATE => [],
    ];

    public function assertKnown(string $canonicalState): void
    {
        if (!in_array($canonicalState, StateResolver::STATES, true)) {
            throw new \InvalidArgumentException(
                "Unknown customer installation state [{$canonicalState}].",
            );
        }
    }

    public function assertTransition(string $from, string $to): void
    {
        $this->assertKnown($from);
        $this->assertKnown($to);

        if ($from === StateResolver::INVALID_STATE || $to === StateResolver::INVALID_STATE) {
            throw new \LogicException(
                'Invalid customer installation state transition [' . $from . ' -> ' . $to . '].',
            );
        }

        if ($from !== $to && !in_array($to, self::TRANSITIONS[$from], true)) {
            throw new \LogicException(
                "Invalid customer installation state transition [{$from} -> {$to}].",
            );
        }
    }
}
