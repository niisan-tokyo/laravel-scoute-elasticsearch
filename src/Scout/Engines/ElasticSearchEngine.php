<?php

namespace Niisan\Laravel\Scout\Engines;

use Elasticsearch\Client;
use Laravel\Scout\Builder;
use Laravel\Scout\Engines\Engine;

class ElasticSearchEngine extends Engine
{
    private $client;

    public function __construct(Client $client)
    {
        $this->client = $client;
    }

    public function update($models)
    {
        if ($models->isEmpty()) {
            return;
        }

        $params = ['body' => []];
        foreach ($models as $model) {
            if (empty($searchableData = $model->toSearchableArray())) {
                continue;
            }

            $params['body'][] = [
                'update' => [
                    '_id' => $model->getKey(),
                    '_index' => $model->searchableAs(),
                    'retry_on_conflict' => 3
                ]
            ];

            // $search = ['search' => collect($searchableData)->flatten()->reduce(function ($acc, $cur) {
            //     return $acc . ' ' . $cur;
            // }, '')];

            $params['body'][] = [
                'doc' => $searchableData,
                'doc_as_upsert' => true
            ];
        }

        $this->client->bulk($params);
    }

    public function delete($models)
    {
        if ($models->isEmpty()) {
            return;
        }

        $params = ['body' => []];
        foreach ($models as $model) {
            $params['body'][] = [
                'delete' => [
                    '_id' => $model->getKey(),
                    '_index' => $model->searchableAs()
                ]
            ];
        }

        $this->client->bulk($params);
    }

    public function search(Builder $builder)
    {
        return $this->performSearch($builder);
    }

    public function paginate(Builder $builder, $perPage, $page)
    {
        $res = $this->performSearch($builder, [
            'perPage' => $perPage,
            'from' => $perPage * ($page - 1)
        ]);

        $res['nbPage'] = $res['hits']['total'] / $perPage;
        return $res;
    }

    public function mapIds($results)
    {
        return collect($results['hits']['hits'])->pluck('_id')->values();
    }

    public function map(Builder $builder, $results, $model)
    {
        $hits = $results['hits']['hits'];
        if (count($hits) === 0) {
            return $model->newCollection();
        }

        $objectIds = collect($hits)->pluck('_id')->values()->all();
        $objectIdPositions = array_flip($objectIds);

        return $model->getScoutModelsByIds($builder, $objectIds)
        ->filter(function ($model) use ($objectIds) {
            return in_array($model->getScoutKey(), $objectIds);
        })->sortBy(function ($model) use ($objectIdPositions) {
            return $objectIdPositions[$model->getScoutKey()];
        })->values();
    }

    public function getTotalCount($results)
    {
        return $results['hits']['total'];
    }

    public function flush($model)
    {
        //
    }

    private function performSearch(Builder $builder, $options = [])
    {
        $search = explode(' ', str_replace('　', ' ', $builder->query['search']));

        $query = [
            'query' => [
                'query_string' => [
                    'query' => '*' . implode('* AND *', $search) . '*'
                ]
            ]
        ];

        $params = [
            'index' => $builder->index ?? $builder->model->searchableAs(),
            'body' => $query,
            'size' => $options['perPage'] ?? null,
            'from' => $options['from'] ?? 0
        ];

        if ($builder->callback) {
            return call_user_func(
                $builder->callback,
                $this->client,
                $builder->query,
                $options
            );
        }

        return $this->client->search($params);
    }
}