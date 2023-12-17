<?php

use wlib\Di\DiBox;
use wlib\Di\DiBoxProvider;
use wlib\Di\DiException;
use wlib\Di\DiNotFoundException;

class A {}
class B { public function __construct(private A $a) {} }
class C { public function __construct(public string $s) {} }

$box = new DiBox();

test('Direct binding', function() use (&$box)
{
	$box->bind(B::class);

	expect($box->get(B::class))->toBeInstanceOf(B::class);
});

test('Classname binding', function() use (&$box)
{
	$box->bind('classname', A::class);

	expect($box->get('classname'))->toBeInstanceOf(A::class);
});

test('Closure binding', function() use (&$box)
{
	$box->bind('closure', function($box, $args)
	{
		return new B($box->make(A::class));
	});

	expect($box->get('closure'))->toBeInstanceOf(B::class);
});

test('Scalar bindings', function() use(&$box)
{
	$box->bind('scalar.string', 'Hello world');
	$box->bind('scalar.integer', 2023);
	$box->bind('scalar.array', [1, 2, 3]);

	expect($box->get('scalar.string'))->toBe('Hello world');
	expect($box->get('scalar.integer'))->toBe(2023);
	expect($box->get('scalar.array'))->toMatchArray([1, 2, 3]);
});

test('ArrayAccess get/set', function() use (&$box)
{
	$box['make.b'] = function ($box, $args) { return new B(new A()); };
	$box['integer'] = 4046;
	$box['list'] = ['a', 'b', 'c'];
	$box['class.a'] = A::class;

	expect($box['make.b'])->toBeInstanceOf(B::class);
	expect($box['integer'])->toBe(4046);
	expect($box['list'])->toMatchArray(['a', 'b', 'c']);
	expect($box['class.a'])->toBeInstanceOf(A::class);
});

test('ArrayAccess isset/unset', function() use (&$box)
{
	unset($box['class.a']);
	expect(isset($box['integer']))->toBeTrue();
	expect(isset($box['class.a']))->toBeFalse();
});

test('singleton()', function() use(&$box)
{
	$box->singleton('singleton', function($box, $args)
	{
		return new C($args[0]);
	});

	$singleton = $box->get('singleton', ['a']);
	expect($singleton)->toBeInstanceOf(C::class);
	expect($singleton->s)->toBe('a');

	$singleton = $box->get('singleton', ['b']);
	expect($singleton)->toBeInstanceOf(C::class);
	expect($singleton->s)->toBe('a');
});

test('has()',function() use (&$box)
{
	expect($box->has('unknown'))->toBeFalse();
	expect($box->has('closure'))->toBeTrue();
});

test('Add a provider', function() use(&$box)
{
	class TestProvider implements DiBoxProvider
	{
		public function provide(DiBox $box)
		{
			$box->bind('from.provider', C::class);
		}
	}

	$box->register(TestProvider::class);

	expect($box->get('from.provider', ['s' => 'test']))->toBeInstanceOf(C::class);
});

test('DiException on wrong Di provider registered', function () use (&$box)
{
	class WrongProvider {}
	$box->register(WrongProvider::class);
})
->throws(DiException::class);

test('DiException on closure return error', function() use(&$box)
{
	$box->bind('closure.error', function($box, $arg)
	{
		new stdClass();
	});

	$box->get('closure.error');
})
->throws(
	DiException::class,
	'Unable to retreive "closure.error" dependency. Closure bound must return an instance.'
);

test('DiNotFoundException', function() use (&$box)
{
	$box->get('unknown');
})
->throws(DiNotFoundException::class, 'Dependency "unknown" not found.');

test('DiException on make non-existent classname', function() use (&$box)
{
	$box->make('Foo');
})
->throws(DiException::class, 'Class "Foo" does not exists.');

test('DiException on make non-instanciable classname', function() use (&$box)
{
	class Foo { private function __construct() {} };
	$box->make('Foo');
})
->throws(DiException::class, 'Class "Foo" is not an instanciable class.');

test('DiException on make without mandatory parameter', function() use (&$box)
{
	class Bar { public function __construct($arg) {} };
	$box->make('Bar');
})
->throws(DiException::class, 'Could not resolve "Parameter #0 [ <required> $arg ]" in "Bar" class.');