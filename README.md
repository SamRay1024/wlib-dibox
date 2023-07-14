# wlib/dibox

DiBox est un conteneur d'injection de dépendances. Il suit la directive [PSR-11](https://www.php-fig.org/psr/psr-11/).

## Utilisation

```php
use wlib\Tools\DiBox;

$box = new DiBox();
```

### Instancier vos objets

Le conteneur peut servir pour simplement instancier vos objets :

```php
// Créer une instance de Foo\Bar
$bar = $box->make(Foo\Bar::class);

// Passer des arguments au constructeur
$bar = $box->make(Foo\Bar::class, ['a', 'b']); // indexés
$bar = $box->make(Foo\Bar::class, ['greetings' => 'Hello']); // nommés
```

L'avantage de passer par le conteneur est de disposer de la **résolution automatique des classes** que vos constructeurs attendent :

```php
class Bar { public function __construct(public Baz $baz); }
class Baz {}

$bar = $box->make(Bar::class);

// $bar->baz sera une instance de Baz
```

### Déclarer vos dépendances

Instancier c'est déjà pas mal, mais définir des dépendances c'est encore mieux, surtout pour construire votre application :

#### Dépendance directe

Pas forcément le plus utile, mais ça fonctionne :

```php
$box->bind(Foo\Bar::class);
$bar = $box->get(Foo\Bar::class); // instance de Foo\Bar
```

#### Créer un alias

Pour mettre des surnoms à tout le monde (ou mettre de l'ordre dans votre conteneur, c'est selon) :

```php
$box->bind('MyBar', Foo\Bar::class);
$bar = $box->get('MyBar'); // toujours une instance de Foo\Bar mais depuis son p'tit nom
```

#### Instance existante

Si besoin :

```php
$bar = new Foo\Bar();
$box->bind('MyBar', $bar);
$samebar = $box->get('MyBar'); // toujours pareil !
```

#### Fermeture de création

Ou `Closure` pour les initiés ;-). C'est là que les choses sérieuses commencent et que ça devient intéressant :

```php
$box->bind('MyBaz', function($box, $args)
{
	return new Foo\Baz($box->get('MyBar'));
});

$baz = $box->get('MyBaz'); // instance de Foo\Baz prête à bosser, classe non ?
```

Vous voyez donc l'étendue des possibles qui s'offre devant vos yeux ébahis !

#### Valeurs scalaires

Qui peut le plus, peut le moins, vous pouvez aussi stocker de simples valeurs dans le conteneur :

```php
$box->bind('one.string', 'DiBox has all of a great one !');
$box->bind('one.integer', 2023);
$box->bind('one.array', [1, 2, 3]);

echo $box->get('one.string'));  // Affiche "DiBox has ...')
echo $box->get('one.integer')); // Affiche "2023"
echo $box->get('one.array'));   // Attention piège, ça affiche quoi ?
```

#### Mode "ArrayAccess"

Le conteneur implémente l'interface `ArrayAccess` :

```php
$box['make.b']   = function ($box, $args) { return new B(new A()); };
$box['integer']  = 4046;
$box['list']     = ['a', 'b', 'c'];
$box['class.a']  = A::class;

$b        = $box['make.b'];  // instance de B
$iInteger = $box['integer']; // 4096
$aList    = $box['list'];    // cf. 5 lignes au dessus
$a        = $box['class.a']; // bon, vous maîtrisez normalement
```

Du coup, vous pouvez faire usage de `isset()` et `unset()` :

```php
unset($box['class.a']);
isset($box['class.a']); // >> false
```

#### Dépendances partagées (singletons)

Vous pouvez, si besoin, partager des dépendances : le mode "singleton" (mais en mieux, car, comme vous le savez [faire des vrais singletons n'est pas toujours une bonne pratique même si c'est pratique](https://doc.nette.org/fr/dependency-injection/global-state)) :

```php
class Counter
{
    protected $count = 0;
    
    public function increment(): int
    {
        return $this->count++;
    }
}

$box->singleton(Counter::class);

echo $box->get(Counter::class)->increment(); // 0
echo $box->get(Counter::class)->increment(); // 1
echo $box->get(Counter::class)->increment(); //	2
```

### Autres méthodes

Ci-après, à connaître :

```php
$box->has('MyBar');    // pour vérifier la présense d'une dépendance
$box->remove('MyBar'); // pour retirer cette dépendance que vous ne voulez plus voir
$box->empty();         // pour vider le conteneur
```

## Fournisseurs de dépendances

Vous êtes encore là ? C'est bien beau, on peut déclarer plein de dépendances et alimenter comme on veut le conteneur mais vous êtes probablement en train de développer une application qui ne connaît peut être pas encore toutes les dépendances qu'elle aura à gérer, ou, vous voulez simplement organiser tout ça dans vos différents namespaces / packages.

Vous avez donc besoin de créer des fournisseurs, qui viendront injecter les dépendances dont ils ont la responsabilité dans l'un de vos conteneurs.

Il est donc temps d'implémenter le contrat `wlib\Tools\DiBoxProvider` :

```php
// Exemple (classique ?) d'un fournisseur des services HTTP d'une application
class HttpProvider implements DiBoxProvider
{
	public function register(DiBox $box)
	{
		$box->bind('http.request', function($box, $args)
		{
			return MyApp\Http\Request();
		});

		$box->bind('http.response', function($box, $args)
		{
			return MyApp\Http\Response($box['http.request']);
		});
	}
}

// Et pour le fournir au conteneur, rien de plus simple :
$box->provide(new HttpProvider);

$response = $box->get('http.response'); // Et vous avez une réponse HTTP prête à servir vos applications / API
```

Voilà, vous maîtrisez ! À vous de jouer.

## Exceptions

Il est possible, en cas de malfonction, volontaire ou fortuite, que `DiBox` lève les exceptions suivantes :

- `wlib\Tools\DiException` : levée au moindre truc qui chagrine le conteneur,
- `wlib\Tools\DiNotFoundException` : conforme à **PSR-11**, levée si vous tentez d'accèder à une dépendance qui n'existe pas.

## Tests unitaires

N'hésitez pas à prendre connaissance du fichier `/tests/Unit/DiBoxTest.php` qui vous donnera quelques détails techniques supplémentaires sur l'utilisation de `DiBox`.

Les tests unitaires font usage de la libraire [Pest](https://pestphp.com/).