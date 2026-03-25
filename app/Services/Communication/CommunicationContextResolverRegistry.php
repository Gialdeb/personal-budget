<?php

namespace App\Services\Communication;

use App\Contracts\CommunicationContextResolverInterface;
use App\Services\Communication\ContextResolvers\AccountInvitationCommunicationContextResolver;
use App\Services\Communication\ContextResolvers\ImportCommunicationContextResolver;
use App\Services\Communication\ContextResolvers\UserCommunicationContextResolver;
use InvalidArgumentException;

class CommunicationContextResolverRegistry
{
    /**
     * @param  array<int, CommunicationContextResolverInterface>  $resolvers
     */
    public function __construct(
        protected array $resolvers = [],
    ) {
        $this->resolvers = $this->resolvers !== []
            ? $this->resolvers
            : [
                app(AccountInvitationCommunicationContextResolver::class),
                app(UserCommunicationContextResolver::class),
                app(ImportCommunicationContextResolver::class),
            ];
    }

    public function for(string $contextType): CommunicationContextResolverInterface
    {
        foreach ($this->resolvers as $resolver) {
            if ($resolver->supports($contextType)) {
                return $resolver;
            }
        }

        throw new InvalidArgumentException("No communication context resolver found for context type [{$contextType}].");
    }
}
