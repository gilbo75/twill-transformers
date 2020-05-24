<?php

namespace A17\TwillTransformers;

use App\Support\Templates;
use A17\Twill\Models\Model;
use Illuminate\Support\Str;
use A17\TwillTransformers\Behaviours\HasConfig;
use A17\TwillTransformers\Exceptions\Repository;
use A17\TwillTransformers\Behaviours\ClassFinder;
use Astrotomic\Translatable\Contracts\Translatable;
use A17\TwillTransformers\Exceptions\Transformer as TransformerException;

trait RepositoryTrait
{
    use ClassFinder, HasConfig;

    public function makeViewData($subject = [])
    {
        return $this->makeViewDataTransformer($subject)->transform();
    }

    public function makeViewDataTransformer($subject = [])
    {
        if (is_numeric($subject)) {
            $subject = $this->getById($subject);
        }

        $transformer = app($this->getTransformerClass());

        return $transformer->setData([
            'template_name' =>
                $this->getTemplateName($subject, $transformer) ?? null,
            'type' => $this->getRepositoryType(),
            'data' => $subject,
            'active_locale' => $this->getActiveLocale($subject),
        ]);
    }

    /**
     * @return string
     */
    public function getTemplateName(...$objects)
    {
        $objects[] = $this;

        return collect($objects)->reduce(
            fn($name, $object) => $name ??
                $this->getTemplateNameFromObject($object),
        );
    }

    /**
     * @return string
     */
    public function setTemplateName($templateName)
    {
        $this->templateName = $templateName;
    }

    protected function getActiveLocale($model)
    {
        if (filled($model->translations ?? null)) {
            return $model->translations
                ->pluck('locale')
                ->contains($locale = locale())
                ? $locale
                : fallback_locale();
        }

        return locale();
    }

    protected function getTemplateNameFromObject($object)
    {
        $templateName =
            $object->templateName ?? ($object->template_name ?? null);

        if (blank($templateName)) {
            try {
                $templateName = $object['templateName'];
            } catch (\Throwable $exception) {
            }
        }

        if (blank($templateName)) {
            try {
                $templateName = $object['template_name'];
            } catch (\Throwable $exception) {
            }
        }

        if (blank($templateName)) {
            try {
                $templateName = $object->getTemplateName();
            } catch (\Throwable $exception) {
            }
        }

        if (blank($templateName)) {
            try {
                $templateName = $object->getTemplate();
            } catch (\Throwable $exception) {
            }
        }

        return $templateName;
    }

    public function getTransformerClass()
    {
        if (filled($this->transformerClass ?? null)) {
            return $this->transformerClass;
        }

        if ($class = $this->inferTransformerClassFromRepositoryName()) {
            return $class;
        }

        TransformerException::missingOnRepository();
    }

    public function getRepositoryType()
    {
        return $this->repositoryType ?? null;
    }

    public function inferTransformerClassFromRepositoryName()
    {
        $class = (string) Str::of(__CLASS__)
            ->afterLast('\\')
            ->beforeLast('Repository');

        return $this->findTransformerClass($class);
    }
}
