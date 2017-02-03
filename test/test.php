<?php

use mindplay\PayloadService;
use mindplay\readable;

require dirname(__DIR__) . '/vendor/autoload.php';

configure()->enableVerboseOutput();

test(
    'can encode/decode payloads',
    function () {
        $valid = [
            ["hello"],
            ["hello" => "world"],
            ["a", "b", "c"],
            ["a" => "b", "c" => "d"],
            ["a", "b", "c" => "d"],
            ["a" => "b", "c", "d"],
            ["src" => "profile/18374.jpg", "size" => "160x160"],
            ["size" => ["w" => "128", "h" => "128"]],
        ];

        $service = new PayloadService();

        foreach ($valid as $payload) {
            ksort($payload, SORT_NATURAL); // encoding affects order

            $encoded = $service->encode($payload);
            $decoded = $service->decode($encoded);

            eq($payload, $decoded, "encodes " . readable::value($payload) . " as {$encoded} and back");
        }
    }
);

test(
    'fails to decode invalid/mangled payloads',
    function () {
        $invalid = [
            "YIA8MD1hJjE9YiZjPWQ_",
            "_YIA8MD1hJjE9YiZjPWQ",
            "???",
            "",
        ];

        $service = new PayloadService();

        foreach ($invalid as $payload) {
            expect(
                InvalidArgumentException::class,
                "should throw for invalid payload: {$payload}",
                function () use ($service, $payload) {
                    $service->decode($payload);
                }
            );
        }
    }
);

test(
    'prevents creation of payloads exceeding a given size',
    function () {
        $service = new PayloadService(20);

        expect(
            InvalidArgumentException::class,
            "should throw if encoded string exceeds specified size",
            function () use ($service) {
                $service->encode(["01234567890123456789"]);
            }
        );
    }
);

exit(run());
