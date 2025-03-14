<?php

/**
 * This file is part of ILIAS, a powerful learning management system
 * published by ILIAS open source e-Learning e.V.
 *
 * ILIAS is licensed with the GPL-3.0,
 * see https://www.gnu.org/licenses/gpl-3.0.en.html
 * You should have received a copy of said license along with the
 * source code, too.
 *
 * If this is not the case or you just want to try ILIAS, you'll find
 * us at:
 * https://www.ilias.de
 * https://github.com/ILIAS-eLearning
 *
 *********************************************************************/

declare(strict_types=1);

use ILIAS\Notifications\Interfaces\PushProviderInterface;
use ILIAS\Setup\Artifact;
use ILIAS\Setup\Artifact\ArrayArtifact;
use ILIAS\Setup\Artifact\BuildArtifactObjective;
use ILIAS\Setup\ImplementationOfInterfaceFinder;

class PushProviderObjective extends BuildArtifactObjective
{
    /**
     * @return \Generator<PushProviderInterface>
     */
    public function getArtifacts(): \Generator
    {
        foreach (require $this->getArtifactPath() as $class) {
            yield new $class();
        }
    }

    public function getArtifactPath(): string
    {
        return 'Services/Notifications/artifacts/push_providers.php';
    }

    public function build(): Artifact
    {
        $finder = new ImplementationOfInterfaceFinder();
        return new ArrayArtifact(iterator_to_array($finder->getMatchingClassNames(PushProviderInterface::class)));
    }
}
