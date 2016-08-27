<?php
namespace Cisco;
/**
 * Description of ConfigOption
 *
 * @author Glenn
 */
class ConfigOption implements \JsonSerializable {
    private $Name;
    private $Value;
    private $Type;
    private $DefaultValue;
    private $Description;
    private $Group;
    /**
     * 
     * @param string $Name
     * @param $DefaultValue
     * @param string $Type
     * @param string $Description
     * @param string $Group
     * @param int  $InputSize
     */
    public function __construct($Name, $DefaultValue, $Type = 'text', $Description = '', $Group = '', $InputSize = 35)
    {
        $this->Name         = $Name;
        $this->DefaultValue = $this->Value = $DefaultValue;
        /* int, intRange, text, listOfListOfOpts */
        $this->Type         = $Type;
        $this->Description  = $Description;
        $this->Group        = $Group;
        $this->InputSize    = $InputSize;
    }
    
    public function getName()
    {
        return $this->Name;
    }

    public function getValue()
    {
        return $this->Value;
    }

    public function getType()
    {
        return $this->Type;
    }

        
    public function getDefaultValue()
    {
        return $this->DefaultValue;
    }

    public function getDescription()
    {
        return $this->Description;
    }

    public function getGroup()
    {
        return $this->Group;
    }

    public function setName($Name)
    {
        $this->Name = $Name;
    }

    public function setValue($Value)
    {
        $this->Value = $Value;
    }

    public function setDefaultValue($DefaultValue)
    {
        $this->DefaultValue = $DefaultValue;
    }

    public function setDescription($Description)
    {
        $this->Description = $Description;
    }

    public function setGroup($Group)
    {
        $this->Group = $Group;
    }

    public function jsonSerialize()
    {
        
        if($this->Type == 'listOfListOfOpts') {
            foreach($this->Value as $Entry) {
                $Value[$Entry->GetName()] = $Entry;
            }
        } else {
            $Value = $this->Value;
        }
        
        return [
            'Name' => $this->Name,
            'Value' => $Value,
            'Type' => $this->Type,
            'Description' => $this->Description,
            'Group' => $this->Group,
            'InputSize' => $this->InputSize
        ];
    }
}
