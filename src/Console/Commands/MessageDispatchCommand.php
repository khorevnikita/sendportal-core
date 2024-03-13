<?php

declare(strict_types=1);

namespace Sendportal\Base\Console\Commands;

use Illuminate\Console\Command;
use Illuminate\Database\Eloquent\Collection as EloquentCollection;
use Illuminate\Support\Facades\Log;
use Sendportal\Base\Events\MessageDispatchEvent;
use Sendportal\Base\Models\Campaign;
use Sendportal\Base\Models\CampaignStatus;
use Sendportal\Base\Models\Message;
use Sendportal\Base\Repositories\Campaigns\CampaignTenantRepositoryInterface;
use Sendportal\Base\Services\Campaigns\CampaignDispatchService;

class MessageDispatchCommand extends Command
{
    /** @var string */
    protected $signature = 'sp:messages:dispatch';

    /** @var string */
    protected $description = 'Dispatch all messages waiting in the queue';

    const MESSAGES_BATCH_SIZE = 500;

    public function handle(): void
    {

        $messages = $this->getNextMessages();
        $count = count($messages);

        if (!$count) {
            return;
        }

        $this->info('Dispatching messages count=' . $count);

        foreach ($messages as $message) {
            $this->info('Dispatching message id=' . $message->id);
            event(new MessageDispatchEvent($message));
        }

        $this->info('Finished dispatching messages');
    }

    /**
     * Get all queued campaigns.
     */
    protected function getNextMessages(): EloquentCollection
    {
        return Message::whereNull('sent_at')
            ->whereNull('queued_at')
            ->take(self::MESSAGES_BATCH_SIZE)
            ->get();
    }
}
