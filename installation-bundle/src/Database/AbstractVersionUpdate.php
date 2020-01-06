<?php

declare(strict_types=1);

/*
 * This file is part of Contao.
 *
 * (c) Leo Feyer
 *
 * @license LGPL-3.0-or-later
 */

namespace Contao\InstallationBundle\Database;

use Contao\CoreBundle\Migration\AbstractMigration;
use Doctrine\DBAL\Connection;
use Symfony\Component\DependencyInjection\ContainerAwareInterface;
use Symfony\Component\DependencyInjection\ContainerAwareTrait;

@trigger_error(sprintf('Using the "%s" class has been deprecated and will no longer work in Contao 5.0. Use the "%s" class instead.', AbstractVersionUpdate::class, AbstractMigration::class), E_USER_DEPRECATED);

/**
 * @deprecated Deprecated since Contao 4.9, to be removed in Contao 5.0; use the
 *             Contao\CoreBundle\Migration\AbstractMigration class instead
 */
abstract class AbstractVersionUpdate implements ContainerAwareInterface
{
    use ContainerAwareTrait;

    /**
     * @var Connection
     */
    protected $connection;

    /**
     * @var array
     */
    protected $messages = [];

    public function __construct(Connection $connection)
    {
        $this->connection = $connection;
    }

    /**
     * @return bool
     */
    public function hasMessage()
    {
        return !empty($this->messages);
    }

    /**
     * @return string
     */
    public function getMessage()
    {
        return implode('', $this->messages);
    }

    /**
     * Checks whether the update should be run.
     *
     * @return bool
     */
    abstract public function shouldBeRun();

    /**
     * Runs the update.
     */
    abstract public function run();

    /**
     * @param string $message
     */
    protected function addMessage($message): void
    {
        $this->messages[] = $message;
    }

    /**
     * @param string $message
     */
    protected function prependMessage($message): void
    {
        array_unshift($this->messages, $message);
    }
}
