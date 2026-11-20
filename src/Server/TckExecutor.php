<?php

declare(strict_types=1);

namespace TiborSrc\A2A\Server;

use TiborSrc\A2A\Id;
use TiborSrc\A2A\Types\Artifact;
use TiborSrc\A2A\Types\Message;
use TiborSrc\A2A\Types\Part;
use TiborSrc\A2A\Types\Role;
use TiborSrc\A2A\Types\Task;
use TiborSrc\A2A\Types\TaskArtifactUpdateEvent;
use TiborSrc\A2A\Types\TaskState;
use TiborSrc\A2A\Types\TaskStatus;
use TiborSrc\A2A\Types\TaskStatusUpdateEvent;

final class TckExecutor implements AgentExecutor
{
    public function execute(RequestContext $context, EventQueue $queue): void
    {
        $id = $context->message->messageId;
        $task = $context->task;

        if (str_starts_with($id, 'tck-message-response')) {
            $queue->write(new Message(
                messageId: Id::uuid(),
                role: Role::Agent,
                parts: [Part::text('Direct message response')],
            ));

            return;
        }

        if (! $task instanceof Task) {
            return;
        }

        $contextId = $task->contextId ?? $task->id;

        if (str_starts_with($id, 'tck-input-required')) {
            $queue->write(new TaskStatusUpdateEvent($task->id, $contextId, TaskStatus::now(TaskState::InputRequired)));

            return;
        }

        if (str_starts_with($id, 'tck-reject-task')) {
            $queue->write(new TaskStatusUpdateEvent($task->id, $contextId, TaskStatus::now(TaskState::Rejected), final: true));

            return;
        }

        if (str_starts_with($id, 'tck-artifact-text')) {
            $this->completeWithArtifact($queue, $task->id, $contextId, new Artifact(Id::uuid(), [Part::text('Generated text content')]));

            return;
        }

        if (str_starts_with($id, 'tck-artifact-file-url')) {
            $this->completeWithArtifact($queue, $task->id, $contextId, new Artifact(
                Id::uuid(),
                [new Part(url: 'https://example.com/output.txt', filename: 'output.txt', mediaType: 'text/plain')],
                'output.txt',
            ));

            return;
        }

        if (str_starts_with($id, 'tck-artifact-file')) {
            $this->completeWithArtifact($queue, $task->id, $contextId, new Artifact(
                Id::uuid(),
                [new Part(raw: 'file-bytes', filename: 'output.txt', mediaType: 'text/plain')],
                'output.txt',
            ));

            return;
        }

        if (str_starts_with($id, 'tck-artifact-data')) {
            $this->completeWithArtifact($queue, $task->id, $contextId, new Artifact(
                Id::uuid(),
                [new Part(data: ['key' => 'value', 'count' => 42])],
            ));

            return;
        }

        if (str_starts_with($id, 'tck-stream-artifact-chunked')) {
            $artifactId = Id::uuid();
            $queue->write(new TaskStatusUpdateEvent($task->id, $contextId, TaskStatus::now(TaskState::Working)));
            $queue->write(new TaskArtifactUpdateEvent($task->id, $contextId, new Artifact($artifactId, [Part::text('chunk-1 ')]), true, false));
            $queue->write(new TaskArtifactUpdateEvent($task->id, $contextId, new Artifact($artifactId, [Part::text('chunk-2')]), true, true));
            $queue->write(new TaskStatusUpdateEvent($task->id, $contextId, TaskStatus::now(TaskState::Completed), final: true));

            return;
        }

        if (str_starts_with($id, 'tck-stream-artifact-file')) {
            $queue->write(new TaskStatusUpdateEvent($task->id, $contextId, TaskStatus::now(TaskState::Working)));
            $queue->write(new TaskArtifactUpdateEvent($task->id, $contextId, new Artifact(
                Id::uuid(),
                [new Part(raw: 'file-bytes', filename: 'output.txt', mediaType: 'text/plain')],
                'output.txt',
            )));
            $queue->write(new TaskStatusUpdateEvent($task->id, $contextId, TaskStatus::now(TaskState::Completed), final: true));

            return;
        }

        if (str_starts_with($id, 'tck-stream-artifact-text')) {
            $queue->write(new TaskStatusUpdateEvent($task->id, $contextId, TaskStatus::now(TaskState::Working)));
            $queue->write(new TaskArtifactUpdateEvent($task->id, $contextId, new Artifact(Id::uuid(), [Part::text('Streamed text content')])));
            $queue->write(new TaskStatusUpdateEvent($task->id, $contextId, TaskStatus::now(TaskState::Completed), final: true));

            return;
        }

        if (str_starts_with($id, 'test-resubscribe-message-id')) {
            $queue->write(new TaskStatusUpdateEvent($task->id, $contextId, TaskStatus::now(TaskState::Working)));
            usleep(50_000);
            $queue->write(new TaskStatusUpdateEvent($task->id, $contextId, TaskStatus::now(TaskState::Completed), final: true));

            return;
        }

        if (str_contains($id, 'stream')) {
            $text = str_contains($id, 'ordering') ? 'Ordered output' : (str_contains($id, '003') ? 'Stream task lifecycle' : 'Stream hello from TCK');
            $queue->write(new TaskStatusUpdateEvent($task->id, $contextId, TaskStatus::now(TaskState::Working)));
            $queue->write(new TaskArtifactUpdateEvent($task->id, $contextId, new Artifact(Id::uuid(), [Part::text($text)])));
            $queue->write(new TaskStatusUpdateEvent($task->id, $contextId, TaskStatus::now(TaskState::Completed), final: true));

            return;
        }

        $queue->write(new TaskStatusUpdateEvent(
            $task->id,
            $contextId,
            TaskStatus::now(TaskState::Completed, new Message(
                messageId: Id::uuid(),
                role: Role::Agent,
                parts: [Part::text('Hello from TCK')],
                contextId: $contextId,
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
            $task->id,
            $task->contextId ?? $task->id,
            TaskStatus::now(TaskState::Canceled),
            final: true,
        ));
    }

    private function completeWithArtifact(EventQueue $queue, string $taskId, string $contextId, Artifact $artifact): void
    {
        $queue->write(new TaskArtifactUpdateEvent($taskId, $contextId, $artifact));
        $queue->write(new TaskStatusUpdateEvent($taskId, $contextId, TaskStatus::now(TaskState::Completed), final: true));
    }
}
