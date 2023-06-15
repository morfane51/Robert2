<?php
declare(strict_types=1);

namespace Robert2\API\Console\Command;

use Illuminate\Database\Eloquent\SoftDeletes;
use Illuminate\Support\Collection;
use Illuminate\Support\Str;
use Robert2\API\Models\Traits\Prunable;
use Symfony\Component\Console\Attribute\AsCommand;
use Symfony\Component\Console\Command\Command;
use Symfony\Component\Console\Input\InputInterface;
use Symfony\Component\Console\Input\InputOption;
use Symfony\Component\Console\Output\OutputInterface;
use Symfony\Component\Finder\Finder;

#[AsCommand(name: 'cleanup')]
class CleanupCommand extends Command
{
    protected function configure()
    {
        /* phpcs:disable Generic.Files.LineLength.TooLong */
        $this
            ->setDescription("\"Nettoie\" les données de l'application.")
            ->setHelp(implode(PHP_EOL, [
                "Cette commande supprimera toute donnée obsolètes de l'application, elle doit être exécutée à intervalle régulier via un CRON par exemple.",
                "",
                "<info>bin/console cleanup</info>",
                "<info>bin/console cleanup --chunk=100</info>",
                "<info>bin/console cleanup --dry-run</info>",
            ]))
            ->addOption('chunk', null, InputOption::VALUE_REQUIRED, "La taille de chaque passe d'éléments à supprimer.", 1000)
            ->addOption('dry-run', 'x', InputOption::VALUE_NONE, "Affiche ce qui se produirait en cas de réelle exécution de la commande.");
        /* phpcs:enable Generic.Files.LineLength.TooLong */
    }

    protected function execute(InputInterface $input, OutputInterface $output): int
    {
        $output->writeln("Récupération des modèles à nettoyer...");

        $isDryRun = (bool) $input->getOption('dry-run');
        $chunkSize = $input->getOption('chunk');

        $models = $this->getPrunableModels();
        if ($models->isEmpty()) {
            $output->writeln("<info>Aucun modèle à nettoyer.</info>");
            return Command::SUCCESS;
        }

        foreach ($models as $model) {
            $instance = new $model['fqdn'];

            if ($isDryRun) {
                $count = $instance->prunable()
                    ->when(
                        in_array(SoftDeletes::class, class_uses_recursive(get_class($instance)), true),
                        function ($query) {
                            $query->withTrashed();
                        }
                    )
                    ->count();
            } else {
                $count = $instance->pruneAll($chunkSize);
            }

            if ($count === 0) {
                $message = $isDryRun
                    ? '<info>Aucun enregistrement obsolètes à supprimer dans le modèle `%2$s`</info>'
                    : '<info>Aucun enregistrement obsolètes supprimé dans le modèle `%2$s`</info>';
            } else {
                $message = $isDryRun
                    ? '<comment>%1$s enregistrement(s) obsolète(s) à supprimer dans le modèle `%2$s`</comment>'
                    : '<info>%1$s enregistrement(s) obsolète(s) supprimé(s) dans le modèle `%2$s`</info>';
            }
            $output->writeln(sprintf("- %s.", vsprintf($message, [$count, $model['name']])));
        }

        return Command::SUCCESS;
    }

    // ------------------------------------------------------
    // -
    // -    Méthodes internes
    // -
    // ------------------------------------------------------

    protected function getPrunableModels(): Collection
    {
        $modelsDir = realpath(APP_FOLDER . DS . 'Models');
        $files = new Collection(
            (new Finder)
                ->in($modelsDir)
                ->files()->name('*.php')
        );

        return $files
            ->map(function ($model) use ($modelsDir) {
                $path = Str::after($model->getRealPath(), $modelsDir . DS);
                $name = str_replace(['/', '.php'], ['\\', ''], $path);
                return [
                    'name' => $name,
                    'fqdn' => sprintf('\\Robert2\\API\\Models\\%s', $name),
                ];
            })
            ->filter(function ($model) {
                $uses = class_uses_recursive($model['fqdn']);
                return in_array(Prunable::class, $uses);
            })
            ->filter(fn($model) => class_exists($model['fqdn']))
            ->sort()
            ->values();
    }
}
