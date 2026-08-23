<?php
use PHPUnit\Framework\TestCase;

final class OrionManagerQueuePolicyTest extends TestCase {
    public function test_exposes_expected_statuses(): void {
        $this->assertSame(
            array('new', 'in_progress', 'resolved', 'dismissed'),
            Orion_Manager_Queue_Policy::statuses()
        );
    }

    public function test_allows_normal_workflow_transitions(): void {
        $this->assertTrue(Orion_Manager_Queue_Policy::can_transition('new', 'in_progress'));
        $this->assertTrue(Orion_Manager_Queue_Policy::can_transition('in_progress', 'resolved'));
        $this->assertTrue(Orion_Manager_Queue_Policy::can_transition('resolved', 'new'));
        $this->assertTrue(Orion_Manager_Queue_Policy::can_transition('dismissed', 'new'));
    }

    public function test_rejects_terminal_to_terminal_and_unknown_transitions(): void {
        $this->assertFalse(Orion_Manager_Queue_Policy::can_transition('resolved', 'dismissed'));
        $this->assertFalse(Orion_Manager_Queue_Policy::can_transition('unknown', 'new'));
        $this->assertFalse(Orion_Manager_Queue_Policy::can_transition('new', 'unknown'));
    }

    public function test_same_status_is_idempotent(): void {
        $this->assertTrue(Orion_Manager_Queue_Policy::can_transition('in_progress', 'in_progress'));
    }

    public function test_priority_is_conservative(): void {
        $this->assertSame('urgent', Orion_Manager_Queue_Policy::normalize_priority('urgent'));
        $this->assertSame('normal', Orion_Manager_Queue_Policy::normalize_priority('unexpected'));
    }

    public function test_only_resolved_and_dismissed_are_terminal(): void {
        $this->assertFalse(Orion_Manager_Queue_Policy::is_terminal('new'));
        $this->assertFalse(Orion_Manager_Queue_Policy::is_terminal('in_progress'));
        $this->assertTrue(Orion_Manager_Queue_Policy::is_terminal('resolved'));
        $this->assertTrue(Orion_Manager_Queue_Policy::is_terminal('dismissed'));
    }
}
