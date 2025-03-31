<?php

/* ==== LICENCE AGREEMENT =====================================================
 *
 * © Cédric Ducarre (20/05/2010)
 * 
 * wlib is a set of tools aiming to help in PHP web developpement.
 * 
 * This software is governed by the CeCILL license under French law and
 * abiding by the rules of distribution of free software. You can use, 
 * modify and/or redistribute the software under the terms of the CeCILL
 * license as circulated by CEA, CNRS and INRIA at the following URL
 * "http://www.cecill.info".
 * 
 * As a counterpart to the access to the source code and rights to copy,
 * modify and redistribute granted by the license, users are provided only
 * with a limited warranty and the software's author, the holder of the
 * economic rights, and the successive licensors have only limited
 * liability.
 * 
 * In this respect, the user's attention is drawn to the risks associated
 * with loading, using, modifying and/or developing or reproducing the
 * software by the user in light of its specific status of free software,
 * that may mean that it is complicated to manipulate, and that also
 * therefore means that it is reserved for developers and experienced
 * professionals having in-depth computer knowledge. Users are therefore
 * encouraged to load and test the software's suitability as regards their
 * requirements in conditions enabling the security of their systems and/or 
 * data to be ensured and, more generally, to use and operate it in the 
 * same conditions as regards security.
 * 
 * The fact that you are presently reading this means that you have had
 * knowledge of the CeCILL license and that you accept its terms.
 * 
 * ========================================================================== */

namespace wlib\Di;

use Closure;
use Psr\Container\ContainerInterface;

/**
 * A simple dependencies injection container.
 *
 * @author Cedric D.
 * @since 07/07/2023
 */
class DiBox implements \ArrayAccess, ContainerInterface
{
	/**
	 * Dependencies array.
	 * @var array
	 */
	private array $aBindings = [];

	/**
	 * Instances array (for singletons).
	 * @var array
	 */
	private array $aInstances = [];

	/**
	 * Providers array.
	 * @var array
	 */
	private array $aProdivers = [];

	/**
	 * Add a dependency.
	 *
	 * `$mConcrete` can be :
	 * 
	 * - `null` : `$sAbstract` must then be the classname to resolve,
	 * - `string` : the real classname to resolve,
	 * - `Closure` : function which is in charge to create the dependency,
	 * - a scalar value to save in the container.
	 *
	 * @param string $sAbstract Dependency alias (or classname if `$mConcrete` is null).
	 * @param mixed $mConcrete Dependency resolution / value.
	 * @param boolean $bSingleton True to make a singleton dependency.
	 * @return self
	 */
	public function bind(string $sAbstract, mixed $mConcrete = null, bool $bSingleton = false): self
	{
		if ($mConcrete === null)
			$mConcrete = $sAbstract;

		elseif (
			!(is_string($mConcrete) && class_exists($mConcrete)) &&
			(is_scalar($mConcrete) || is_array($mConcrete) || is_resource($mConcrete))
		) {
			$this->aBindings[$sAbstract] = $mConcrete;
			return $this;
		}
		elseif (is_object($mConcrete) && !$mConcrete instanceof \Closure)
		{
			$this->aInstances[$sAbstract] = $mConcrete;
			return $this;
		}

		if (!$mConcrete instanceof \Closure)
			$mConcrete = $this->makeClosure($mConcrete);

		$this->aBindings[$sAbstract] = [
			'_concrete_'	=> $mConcrete,
			'_singleton_'	=> $bSingleton
		];

		return $this;
	}

	/**
	 * Make the instanciation closure of the given dependency.
	 *
	 * @param string $sConcrete Name of the dependency.
	 * @return Closure
	 */
	protected function makeClosure(string $sConcrete): Closure
	{
		return function (DiBox $c, $args = []) use ($sConcrete)
		{
			return $c->make($sConcrete, $args);
		};
	}


	/**
	 * Add a singleton dependency (shorthand of bind()).
	 *
	 * @see self::bind()
	 * @param string $sAbstract Dependency alias (or classname if `$mConcrete` is null).
	 * @param mixed $mConcrete Dependency resolution / value.
	 * @return self
	 */
	public function singleton(string $sAbstract, mixed $mConcrete = null): self
	{
		return $this->bind($sAbstract, $mConcrete, true);
	}

	/**
	 * Get a dependency.
	 *
	 * @param string $sAbstract Dependency alias.
	 * @param array $aArgs Optionnals arguments needed by the dependency.
	 * @return mixed
	 * @throws DiNotFoundException when `$sAbstract` is not found.
	 */
	public function get(string $sAbstract, array $aArgs = []): mixed
	{
		$bIsBound = isset($this->aBindings[$sAbstract]);
		$bIsInstance = isset($this->aInstances[$sAbstract]);

		if (!$bIsBound && !$bIsInstance)
			throw new DiNotFoundException($sAbstract);

		if ($bIsInstance)
			return $this->aInstances[$sAbstract];

		if (!isset($this->aBindings[$sAbstract]['_concrete_']))
			return $this->aBindings[$sAbstract];

		$mConcrete = $this->aBindings[$sAbstract]['_concrete_'];

		$instance = ($mConcrete instanceof \Closure
			? $mConcrete($this, $aArgs)
			: $this->make($mConcrete, $aArgs)
		);

		if (!$instance)
			throw new DiException(
				'Unable to retreive "'. $sAbstract .'" dependency. '
				.'Closure bound must return an instance.'
			);

		if ($this->aBindings[$sAbstract]['_singleton_'] === true)
			$this->aInstances[$sAbstract] = $instance;

		return $instance;
	}

	/**
	 * Check if the given dependency exists.
	 *
	 * @param string $sAbstract Dependency alias.
	 * @return boolean
	 */
	public function has(string $sAbstract): bool
	{
		return isset($this->aBindings[$sAbstract]) || isset($this->aInstances[$sAbstract]);
	}

	/**
	 * Remove a dependeny.
	 * 
	 * @param string $sAbstract Dependency alias.
	 * @return void
	 */
	public function remove(string $sAbstract): void
	{
		unset($this->aBindings[$sAbstract], $this->aInstances[$sAbstract]);
	}

	/**
	 * Empty the container.
	 * 
	 * @return void
	 */
	public function empty(): void
	{
		$this->aBindings = [];
		$this->aInstances = [];
	}

	/**
	 * Add a dependencies provider.
	 *
	 * @param string $sProviderFQCN Provider fully qualified class name.
	 * @return DiBoxProvider
	 */
	public function register(string $sProviderFQCN): DiBoxProvider
	{
		if (!in_array(DiBoxProvider::class, class_implements($sProviderFQCN)))
			throw new DiException(sprintf(
				'"%s" must implement "%s" in order to be registered.',
				$sProviderFQCN, DiBoxProvider::class	
			));

		$provider = new $sProviderFQCN();
		$provider->provide($this);

		$this->aProdivers[$sProviderFQCN] = $provider;

		return $provider;
	}
		
	/**
	 * Get registered providers.
	 *
	 * @return array
	 */
	public function getProviders(): array
	{
		return $this->aProdivers;
	}

	/**
	 * Instanciate given class.
	 *
	 * @param string $sClassName Class to instanciate.
	 * @param array $aArgs Arguments to passe to the class constructor.
	 * @return object Instance.
	 * @throws DiException
	 */
	public function make(string $sClassName, array $aArgs = array()): ?object
	{
		if (!class_exists($sClassName))
			throw new DiException('Class "'. $sClassName .'" does not exists.');

		try { $reflection = new \ReflectionClass($sClassName); }
		catch (\Exception $e) {}

		if ($reflection === null || !$reflection->isInstantiable())
			throw new DiException('Class "'. $sClassName .'" is not an instanciable class.');

		$constructor = $reflection->getConstructor();

		if ($constructor === null)
			return $reflection->newInstance();

		$aParameters = $constructor->getParameters();
		$aInstanceArgs = array();

		/* @var $parameter ReflectionParameter */
		foreach ($aParameters as $i => $parameter)
		{
			/* @var $ptype ReflectionNamedType */
			$ptype = $parameter->getType();

			if (array_key_exists($parameter->getName(), $aArgs))
				$aInstanceArgs[] = $aArgs[$parameter->getName()];
			
			elseif (array_key_exists($i, $aArgs))
				$aInstanceArgs[] = $aArgs[$i];

			elseif ($ptype && !$ptype->isBuiltin())
			{
				try { $aInstanceArgs[] = $this->make($ptype->getName()); }
				catch (DiException $e)
				{
					if ($parameter->isOptional())
						$aInstanceArgs[] = $parameter->getDefaultValue();
					else
						throw $e;
				}
			}

			else
			{
				if ($parameter->isDefaultValueAvailable())
					$aInstanceArgs[] = $parameter->getDefaultValue();
				else
					throw new DiException(
						'Could not resolve "'. $parameter .'" in "'
						. $parameter->getDeclaringClass()->getName() .'" class.'
					);
			}
		}

		return $reflection->newInstanceArgs($aInstanceArgs);
	}

	/**
	 * ArrayAccess exits.
	 * 
	 * @see self::has()
	 * @param string $sAbstract Dependency alias.
	 * @return boolean
	 */
	public function offsetExists(mixed $mAbstract): bool
	{
		return $this->has($mAbstract);
	}

	/**
	 * ArrayAccess get.
	 * 
	 * @see self::get()
	 * @param string $sAbstract Dependency alias.
	 * @return mixed
	 */
	public function offsetGet(mixed $mAbstract): mixed
	{
		return $this->get($mAbstract);
	}

	/**
	 * ArrayAccess set.
	 * 
	 * @see self::bind()
	 * @param string $sAbstract Dependency alias.
	 * @param mixed $mConcrete Dependency resolution / value.
	 */
	public function offsetSet($mAbstract, $mConcrete): void
	{
		$this->bind($mAbstract, $mConcrete);
	}

	/**
	 * ArrayAccess unset.
	 * 
	 * @param string $sAbstract Dependency alias.
	 */
	public function offsetUnset(mixed $mAbstract): void
	{
		$this->remove($mAbstract);
	}
}