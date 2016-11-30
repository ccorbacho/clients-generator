<?php

require_once (__DIR__. '/NG2TypescriptGeneratorBase.php');
require_once (__DIR__. '/GeneratedFileData.php');

class ClassesGenerator extends NG2TypescriptGeneratorBase
{

    function __construct($serverMetadata)
    {
        parent::__construct($serverMetadata);
    }

    public function generate()
    {
        $result = array_merge(
            $this->createClassTypes()
        );

        return $result;
    }

    function createClassTypes()
    {
        $classTypes = array();

        foreach ($this->serverMetadata->classTypes as $class) {
            $classTypes[] = $this->createClassTypeExp($class);
        }

        $fileContent = "
import {KalturaServerObject, DependentProperty, DependentPropertyTarget, KalturaPropertyTypes} from \"./utils/kaltura-server-object\";
import * as kenums from \"./kaltura-enums\";

{$this->utils->buildExpression($classTypes,NewLine . NewLine)}
";

        $result = array();
        $file = new GeneratedFileData();
        $file->path = "kaltura-types.ts";
        $file->content = $fileContent;
        $result[] = $file;
        return $result;
    }

    function createClassTypeExp(ClassType $class)
    {
        $classTypeName = Utils::upperCaseFirstLetter($class->name);
        $desc = $class->description;

        $content = $this->createContentFromClass($class);

        $result = "
{$this->getBanner()}
{$this->utils->createDocumentationExp('',$desc)}
export {$this->utils->ifExp($class->abstract, "abstract", "")} class {$classTypeName} extends {$this->utils->ifExp($class->base, $class->base,"KalturaServerObject")} {

    get objectType() : string{
        return '{$class->name}';
    }
    {$this->utils->buildExpression($content->properties, NewLine, 1)}

    constructor()
    {
        super();

        {$this->utils->buildExpression($content->constructorContent, NewLine, 2 )}
    }

    setDependency(...dependency : DependentProperty[]) : {$classTypeName}
    {
        super.setDependency(...dependency);
        return this;
    }

    build():any {
        return Object.assign(
            super.build(),
            {
                {$this->utils->buildExpression($content->buildContent,  ',' . NewLine, 4)}
            });
    };

    setData(handler : (request :  {$classTypeName}) => void) :  {$classTypeName}
    {
        if (handler)
        {
            handler(this);
        }

        return this;
    }
}";

        return $result;
    }


    function createContentFromClass(ClassType $class)
    {
        $result = new stdClass();
        $result->properties = array();
        $result->buildContent = array();
        $result->constructorContent = array();


        $result->buildContent[] = "objectType : \"{$class->name}\"";


        if (count($class->properties) != 0)
        {
            foreach($class->properties as $property) {
                // update the build function
                $result->buildContent[] = $this->requestBuildExp($property->name, $property->type,false);

                // update the properties declaration
                $ng2ParamType = $this->toNG2TypeExp($property->type, $property->typeClassName);
                $result->properties[] = "
get {$property->name}() : {$ng2ParamType}
{
    return <{$ng2ParamType}>this.objectData['{$property->name}'];
}

set {$property->name}(value : {$ng2ParamType})
{
    this.objectData['{$property->name}'] = value;
}";
                if ($property->type == KalturaServerTypes::ArrayObject) {
                    $result->constructorContent[] = "this.objectData['{$property->name}'] = [];";
                }
            }
        }

        return $result;
    }

    protected function toNG2TypeExp($type, $typeClassName, $resultCreatedCallback = null)
    {
        return parent::toNG2TypeExp($type,$typeClassName,function($type,$typeClassName,$result)
        {
            switch($type) {
                case KalturaServerTypes::Enum:
                    $result = 'kenums.' . $result;
                    break;
            }

            return $result;
        });
    }
}