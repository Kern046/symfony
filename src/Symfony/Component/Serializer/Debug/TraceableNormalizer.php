<?php

/*
 * This file is part of the Symfony package.
 *
 * (c) Fabien Potencier <fabien@symfony.com>
 *
 * For the full copyright and license information, please view the LICENSE
 * file that was distributed with this source code.
 */

namespace Symfony\Component\Serializer\Debug;

use Symfony\Component\Serializer\DataCollector\SerializerDataCollector;
use Symfony\Component\Serializer\Normalizer\CacheableSupportsMethodInterface;
use Symfony\Component\Serializer\Normalizer\DenormalizerAwareInterface;
use Symfony\Component\Serializer\Normalizer\DenormalizerInterface;
use Symfony\Component\Serializer\Normalizer\NormalizerAwareInterface;
use Symfony\Component\Serializer\Normalizer\NormalizerInterface;
use Symfony\Component\Serializer\SerializerAwareInterface;
use Symfony\Component\Serializer\SerializerInterface;

/**
 * Collects some data about normalization.
 *
 * @author Mathias Arlaud <mathias.arlaud@gmail.com>
 *
 * @internal
 */
class TraceableNormalizer implements NormalizerInterface, DenormalizerInterface, SerializerAwareInterface, NormalizerAwareInterface, DenormalizerAwareInterface, CacheableSupportsMethodInterface
{
    public function __construct(
        private NormalizerInterface|DenormalizerInterface $normalizer,
        private SerializerDataCollector $dataCollector,
    ) {
    }

    /**
     * {@inheritDoc}
     */
    public function normalize(mixed $object, string $format = null, array $context = []): array|string|int|float|bool|\ArrayObject|null
    {
        if (!$this->normalizer instanceof NormalizerInterface) {
            throw new \BadMethodCallException(sprintf('The "%s()" method cannot be called as nested normalizer doesn\'t implements "%s".', __METHOD__, NormalizerInterface::class));
        }

        $startTime = microtime(true);
        $normalized = $this->normalizer->normalize($object, $format, $context);
        $time = microtime(true) - $startTime;

        if ($traceId = ($context[TraceableSerializer::DEBUG_TRACE_ID] ?? null)) {
            $this->dataCollector->collectNormalization($traceId, \get_class($this->normalizer), $time);
        }

        return $normalized;
    }

    /**
     * {@inheritDoc}
     *
     * @param array $context
     */
    public function supportsNormalization(mixed $data, string $format = null /*, array $context = [] */): bool
    {
        if (!$this->normalizer instanceof NormalizerInterface) {
            return false;
        }

        $context = \func_num_args() > 2 ? func_get_arg(2) : [];

        return $this->normalizer->supportsNormalization($data, $format, $context);
    }

    /**
     * {@inheritDoc}
     */
    public function denormalize(mixed $data, string $type, string $format = null, array $context = []): mixed
    {
        if (!$this->normalizer instanceof DenormalizerInterface) {
            throw new \BadMethodCallException(sprintf('The "%s()" method cannot be called as nested normalizer doesn\'t implements "%s".', __METHOD__, DenormalizerInterface::class));
        }

        $startTime = microtime(true);
        $denormalized = $this->normalizer->denormalize($data, $type, $format, $context);
        $time = microtime(true) - $startTime;

        if ($traceId = ($context[TraceableSerializer::DEBUG_TRACE_ID] ?? null)) {
            $this->dataCollector->collectDenormalization($traceId, \get_class($this->normalizer), $time);
        }

        return $denormalized;
    }

    /**
     * {@inheritDoc}
     *
     * @param array $context
     */
    public function supportsDenormalization(mixed $data, string $type, string $format = null /*, array $context = [] */): bool
    {
        if (!$this->normalizer instanceof DenormalizerInterface) {
            return false;
        }

        $context = \func_num_args() > 3 ? func_get_arg(3) : [];

        return $this->normalizer->supportsDenormalization($data, $type, $format, $context);
    }

    /**
     * {@inheritDoc}
     */
    public function setSerializer(SerializerInterface $serializer)
    {
        if (!$this->normalizer instanceof SerializerAwareInterface) {
            return;
        }

        $this->normalizer->setSerializer($serializer);
    }

    /**
     * {@inheritDoc}
     */
    public function setNormalizer(NormalizerInterface $normalizer)
    {
        if (!$this->normalizer instanceof NormalizerAwareInterface) {
            return;
        }

        $this->normalizer->setNormalizer($normalizer);
    }

    /**
     * {@inheritDoc}
     */
    public function setDenormalizer(DenormalizerInterface $denormalizer)
    {
        if (!$this->normalizer instanceof DenormalizerAwareInterface) {
            return;
        }

        $this->normalizer->setDenormalizer($denormalizer);
    }

    /**
     * {@inheritDoc}
     */
    public function hasCacheableSupportsMethod(): bool
    {
        return $this->normalizer instanceof CacheableSupportsMethodInterface && $this->normalizer->hasCacheableSupportsMethod();
    }

    /**
     * Proxies all method calls to the original normalizer.
     */
    public function __call(string $method, array $arguments): mixed
    {
        return $this->normalizer->{$method}(...$arguments);
    }
}
