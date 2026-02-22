<?php

namespace App\Eloquent\Relations;

use App\Models\Review;
use Illuminate\Database\Eloquent\Builder;
use Illuminate\Database\Eloquent\Collection as EloquentCollection;
use Illuminate\Database\Eloquent\Model;
use Illuminate\Database\Eloquent\Relations\Relation;
use Illuminate\Support\Facades\DB;

/**
 * Room has many Reviews through Bookings (via booking_room pivot).
 * Used by Filament ReviewsRelationManager on RoomResource.
 */
class RoomReviewsRelation extends Relation
{
    public function __construct(Builder $query, Model $parent)
    {
        parent::__construct($query, $parent);
    }

    public function addConstraints(): void
    {
        if (static::$constraints) {
            $bookingIds = $this->parent->bookings()->pluck('bookings.id');
            $this->query->whereIn('booking_id', $bookingIds);
        }
    }

    public function addEagerConstraints(array $models): void
    {
        $roomIds = collect($models)->map->getKey()->filter()->values()->all();
        if ($roomIds === []) {
            $this->eagerKeysWereEmpty = true;
            return;
        }
        $bookingIds = DB::table('booking_room')
            ->whereIn('room_id', $roomIds)
            ->pluck('booking_id');
        $this->query->whereIn('booking_id', $bookingIds);
    }

    public function initRelation(array $models, $relation): array
    {
        foreach ($models as $model) {
            $model->setRelation($relation, $this->related->newCollection());
        }
        return $models;
    }

    public function match(array $models, EloquentCollection $results, $relation): array
    {
        $bookingIdsByRoom = [];
        foreach ($models as $model) {
            $bookingIdsByRoom[$model->getKey()] = $model->bookings()->pluck('bookings.id')->all();
        }
        foreach ($models as $model) {
            $own = $results->filter(fn (Review $review) => in_array($review->booking_id, $bookingIdsByRoom[$model->getKey()] ?? [], true));
            $model->setRelation($relation, $own->values());
        }
        return $models;
    }

    public function getResults(): EloquentCollection
    {
        return $this->query->get();
    }
}
