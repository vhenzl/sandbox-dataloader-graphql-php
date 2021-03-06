<?php

use GraphQL\GraphQL;
use GraphQL\Tests\StarWarsData;
use Overblog\DataLoader\DataLoader;
use Overblog\PromiseAdapter\Adapter\ReactPromiseAdapter;
use React\Promise\Promise;

require __DIR__.'/../vendor/autoload.php';

$calls = 0;
$callsIds = [];
$promiseAdapter = new ReactPromiseAdapter();
$batchLoadFn = function ($ids) use (&$calls, &$callsIds, $promiseAdapter) {
    $callsIds[] = $ids;
    ++$calls;
    $allCharacters = StarWarsData::humans() + StarWarsData::droids();
    $characters = array_intersect_key($allCharacters, array_flip($ids));

    return $promiseAdapter->createAll(array_values($characters));
};
$dataLoader = new DataLoader($batchLoadFn, $promiseAdapter);

$schema = createSchema(
    function ($character) use ($dataLoader) {
        $onFullFilled = function ($value) use ($dataLoader) {
            return $dataLoader->loadMany($value['friends']);
        };

        if ($character instanceof Promise) {
            return $character->then($onFullFilled);
        } else {
            return $onFullFilled($character);
        }
    },
    function ($root, $args) use ($dataLoader) {
        return $dataLoader->load($args['id']);
    }
);

echo "With DataLoader (using reactPHP promise):\n\n";
executeQueries(
    $schema,
    $calls,
    $callsIds,
    new \GraphQL\Executor\Promise\Adapter\ReactPromiseAdapter(),
    function () use ($dataLoader) {
        $dataLoader->clearAll();
    },
    function ($promise)  {
        return DataLoader::await($promise);
    }
);
