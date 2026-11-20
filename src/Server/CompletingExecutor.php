<?php

declare(strict_types=1);

namespace TiborSrc\A2A\Server;

use TiborSrc\A2A\Types\Message;
use TiborSrc\A2A\Types\Part;
use TiborSrc\A2A\Types\Role;
use TiborSrc\A2A\Types\Task;
use TiborSrc\A2A\Types\TaskState;
use TiborSrc\A2A\Types\TaskStatus;
use TiborSrc\A2A\Types\TaskStatusUpdateEvent;

final class CompletingExecutor implements AgentExecutor
{
    public function execute(RequestContext $context, EventQueue $queue): void
    {
        $task = $context->task;

        if (! $task instanceof Task) {
            $queue->write(new Message(
                messageId: $context->message->messageId.'-reply',
                role: Role::Agent,
                parts: [Part::text($this->textFrom($context->message))],
            ));

            return;
        }

        $queue->write(new TaskStatusUpdateEvent(
            taskId: $task->id,
            contextId: $task->contextId ?? $task->id,
            status: TaskStatus::now(TaskState::Completed, new Message(
                messageId: $context->message->messageId.'-reply',
                role: Role::Agent,
                parts: [Part::text($this->textFrom($context->message))],
                contextId: $task->contextId,
                taskId: $task->id,
            )),
            final: true,
        ));
    }

    public function cancel(RequestContext $context, EventQueue $queue): void
    {
        $task = $context->task;

        if (! $task instanceof Task) {
            return;
        }

        $queue->write(new TaskStatusUpdateEvent(
            taskId: $task->id,
            contextId: $task->contextId ?? $task->id,
            status: TaskStatus::now(TaskState::Canceled),
            final: true,
        ));
    }

    private function textFrom(Message $message): string
    {
        foreach ($message->parts as $part) {
            if ($part->text !== null) {
                return $part->text;
            }
        }

        return '';
    }
}
