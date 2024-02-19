<?php

declare(strict_types=1);

namespace Sendportal\Base\Services\Subscribers;

use Exception;
use Illuminate\Support\Collection;
use Illuminate\Support\Facades\DB;
use Sendportal\Base\Events\SubscriberAddedEvent;
use Sendportal\Base\Models\Subscriber;
use Sendportal\Base\Models\Tag;
use Sendportal\Base\Repositories\Subscribers\SubscriberTenantRepositoryInterface;

class ApiSubscriberService
{
    /** @var SubscriberTenantRepositoryInterface */
    protected $subscribers;

    public function __construct(SubscriberTenantRepositoryInterface $subscribers)
    {
        $this->subscribers = $subscribers;
    }

    /**
     * The API provides the ability for the "store" endpoint to both create a new subscriber or update an existing
     * subscriber, using their email as the key. This method allows us to handle both scenarios.
     *
     * @throws Exception
     */
    public function storeOrUpdate(int $workspaceId, Collection $data): Subscriber
    {
        $existingSubscriber = $this->subscribers->findBy($workspaceId, 'email', $data['email']);

        if ($data->has('tags')) {
            $tagNames = $data->get('tags');
            $tagIds = [];

            foreach ($tagNames as $tagName) {
                if(is_numeric($tagName)) {
                    $tag = Tag::where('id',$tagName)->where('workspace_id',$workspaceId)->first();
                } else {
                    $tag = Tag::where('name',$tagName)->where('workspace_id',$workspaceId)->first();
                }

                if(!$tag){
                    $tag = new Tag();
                    $tag->name = $tagName;
                    $tag->workspace_id = $workspaceId;
                    $tag->save();
                }
                $tagIds[] = $tag->id;
            }

            $data->put('tags', $tagIds);
        }

        if (!$existingSubscriber) {
            $subscriber = $this->subscribers->store($workspaceId, $data->toArray());

            event(new SubscriberAddedEvent($subscriber));

            return $subscriber;
        }

        return $this->subscribers->update($workspaceId, $existingSubscriber->id, $data->toArray());
    }

    public function delete(int $workspaceId, Subscriber $subscriber): bool
    {
        return DB::transaction(function () use ($workspaceId, $subscriber) {
            $subscriber->tags()->detach();
            return $this->subscribers->destroy($workspaceId, $subscriber->id);
        });
    }
}
