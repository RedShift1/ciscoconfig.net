<?php
namespace Cisco;

/**
 * Description of ciscoConfBlock
 *
 * @author Glenn
 */
class ConfBlock {
    /**
     * Configuration lines in this block
     * @var array
     */
    private $Lines;
    /**
     * Position of this block in the file
     * @var int
     */
    public $Pos;
    /**
     * true = configuration does not have child items (configuration lines will
     * not be indented)
     * @var bool
     */
    private $Flat;
    
    const POS_BEGIN         = 0;
    const POS_SERVICE       = 10;
    const POS_HOSTNAME      = 20;
    const POS_VRF           = 30;
    const POS_AAA           = 40;
    const POS_CLOCK         = 45;
    const POS_DHCP          = 50;
    const POS_DOT11         = 55;
    const POS_VPDN          = 60;
    const POS_OBJGROUP      = 70;
    const POS_USER          = 80;
    const POS_ISAKMP        = 90;
    const POS_POLMAP        = 100;
    const POS_CRYPTKEYRING  = 110;
    const POS_CRYTPTISAKMP  = 120;
    const POS_CRYPTIPSECTS  = 130;
    const POS_CRYPTIPSECPROF= 140;
    const POS_VLAN          = 145;
    const POS_INT           = 150;
    const POS_ROUTER        = 160;
    const POS_DNS           = 170;
    const POS_NAT           = 180;
    const POS_IPV4ROUTE     = 190;
    const POS_IPV4ACL       = 200;
    const POS_IPV4PFXLIST   = 210;
    const POS_ROUTEMAP      = 220;
    const POS_SNMP          = 230;
    const POS_RADIUS        = 240;
    const POS_IPV6ACL       = 250;
    const POS_LINE          = 260;
    const POS_NTP           = 270;
    const POS_END           = PHP_INT_MAX;
    
    public function __construct($BlockInit, $Pos = self::POS_END, $Flat = false)
    {
        $this->Lines[] = $BlockInit;
        $this->Pos = $Pos;
        $this->Flat = $Flat;
    }
    
    public function addLine($Line)
    {
        $this->Lines[] = $Line;
    }
    
    public function getLines()
    {
        return $this->Lines;
    }
    
    public function __toString()
    {
        $Ret = null;
        $Indent = null;
        $i = 0;
        foreach($this->Lines as $Line) {
            if(is_object($Line)) {
                foreach(explode("\n", $Line->__toString()) as $Line) {
                    if($Line != "") {
                        $Ret .= "{$Indent}{$Line}\n";
                    }
                }
            } else {
                $Ret .= "{$Indent}{$Line}\n";
            }
            if($i == 0 && $this->Flat == false) {
                $Indent = ' ';
            }
            $i++;
        }
        return $Ret;
    }
}
