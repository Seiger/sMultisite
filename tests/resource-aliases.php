<?php

require dirname(__DIR__) . '/src/sMultisite.php';

// No CMS bootstrap or database is needed to exercise the path resolver.
set_error_handler(static function (int $severity, string $message, string $file, int $line): never {
    throw new ErrorException($message, 0, $severity, $file, $line);
});

$service = new Seiger\sMultisite\sMultisite();
$resolver = new ReflectionMethod($service, 'resourceAliases');
$checks = 0;
$resource = static function (int $id, int $parent, string $alias, int $visible = 1): object {
    return new class($id, $parent, $alias, $visible) {
        /**
         * Hold only the resource fields needed by the path resolver.
         *
         * @since 1.2.0
         */
        public function __construct(public int $id, public int $parent, public string $alias, public int $alias_visible) {}

        /**
         * Expose the fixture primary key as a SiteContent model would.
         *
         * @return int Resource identifier.
         * @since 1.2.0
         */
        public function getKey(): int
        {
            return $this->id;
        }
    };
};
$expect = static function (mixed $actual, mixed $expected, string $label) use (&$checks): void {
    if ($actual !== $expected) {
        throw new RuntimeException($label . ': ' . var_export($actual, true));
    }
    $checks++;
};

// Production topology: the model was moved under a newer Samand parent.
foreach ([['samand1', 19738, 19760], ['samand', 19739, 19761], ['samand', 19740, 19762]] as [$alias, $child, $parent]) {
    $tree = [
        $resource($child, $parent, '2002-2022'),
        $resource($parent, 19140, $alias),
        $resource(19140, 7, 'ikco'),
        $resource(7, 2, 'katalog'),
        $resource(2, 0, 'domain', 0),
    ];
    $expected = 'katalog/ikco/' . $alias . '/2002-2022';
    // Rotations cover every possible starting node and reverse dependency order.
    for ($offset = 0; $offset < count($tree); $offset++) {
        $ordered = array_merge(array_slice($tree, $offset), array_slice($tree, 0, $offset));
        $paths = $resolver->invoke($service, $ordered, true);
        $expect($paths[$child], $expected, 'Child before parent');
        $expect($paths[$parent], 'katalog/ikco/' . $alias, 'Parent URL');
    }
    $flat = $resolver->invoke($service, $tree, false);
    $expect($flat[$child], '2002-2022', 'Alias paths disabled');
}

$hidden = [
    $resource(1, 2, 'leaf'),
    $resource(2, 3, 'hidden', 0),
    $resource(3, 4, 'also-hidden', 0),
    $resource(4, 5, 'catalog'),
    $resource(5, 0, 'domain', 0),
];
$paths = $resolver->invoke($service, $hidden, true);
$expect($paths[1], 'catalog/leaf', 'Consecutive hidden ancestors');
$expect($paths[2], 'catalog/hidden', 'Hidden resource remains addressable');
$expect($paths[4], 'catalog', 'Hidden domain root');
$expect($resolver->invoke($service, [], true), [], 'Empty tree');

$mixed = [
    $resource(1, 2, 'leaf'),
    $resource(2, 3, 'visible'),
    $resource(3, 4, 'hidden', 0),
    $resource(4, 0, 'root'),
];
$expect($resolver->invoke($service, $mixed, true)[1], 'root/visible/leaf', 'Hidden grandparent');
$emptyAlias = [$resource(1, 2, 'leaf'), $resource(2, 0, '')];
$expect($resolver->invoke($service, $emptyAlias, true)[1], 'leaf', 'Empty root alias');

foreach ([
    [$resource(1, 2, 'orphan')],
    [$resource(1, 2, 'first'), $resource(2, 1, 'second')],
    [$resource(1, 1, 'self')],
] as $invalidTree) {
    try {
        $resolver->invoke($service, $invalidTree, true);
        throw new RuntimeException('Invalid ancestry was accepted');
    } catch (LogicException $exception) {
        $checks++;
    }
}

restore_error_handler();
echo "Passed {$checks} resource alias checks.\n";
