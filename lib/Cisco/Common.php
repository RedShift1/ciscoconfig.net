<?php
namespace Cisco;

/**
 * IOS configuration common to all Cisco devices (routers, switches, AP's)
 */
abstract class Common extends Config {
    
    public function __construct($Hostname = 'router1.lan.local')
    {
        parent::__construct();
        $this->addLine("service password-encryption");
        $this->addLine("no service pad");
        $this->addLine("no ip forward-protocol nd");
        $this->addLine("no ip source-route");
        /*
         * Don't do this... More trouble than it's worth (VPN, etc...)
          $this->addLine("!          ");
          $this->addLine("!    /\    ");
          $this->addLine("!   /  \   Remove the following line if you plan on using an L2TP VPN tunnel:");
          $this->addLine("!  / !! \  ip arp proxy disable");
          $this->addLine("! <------>  ");
          $this->addLine("ip arp proxy disable");
          $this->addLine("!"); */

        $this->addLine("ip dhcp bootp ignore");
        $this->addLine("clock timezone CET 1 0");
        $this->addLine("clock summer-time CEST recurring last Sun Mar 2:00 last Sun Oct 3:00");
        $this->addLine("service timestamps log datetime localtime show-timezone");
        $this->addLine("logging buffered 4096");

        $Block = $this->addBlock('aaa new-model', ConfBlock::POS_AAA, true);
        $Block->addLine("aaa authentication enable default none");
        $Block->addLine("aaa authentication ppp default local");        
        $Block->addLine("aaa authorization exec default none");
        $Block->addLine("aaa authorization commands 0 default none");
        $Block->addLine("aaa authorization commands 15 default none");
        $this->addLine("no ip http server");
        $this->addLine("no ip http secure-server");

        $this->addOpt('FQDNHostname', 'router1.lan.local', 'string', 'Specify in FQDN format; domain name will also be used for DHCP scopes');
        $this->addOpt('AdminUsername', 'admin', 'string', 'This user will be created with privilege level 15');
        $this->addOpt('AdminPassword', 'admin', 'string');
        
        $this->addOpt('EnableSSH', true, 'bool');
    }
    
    public function addUser($Username, $Password, $PrivLevel = 0)
    {
        $this->addLine("username {$Username} privilege {$PrivLevel} password {$Password}");
    }
    
    
    public function parseFQDN($FQDN)
    {
        return preg_split('#\.#', $this->getOptVal('FQDNHostname'), 2);
    }
    
    
    public function generate()
    {
        $FQDN = $this->parseFQDN($this->getOptVal('FQDNHostname'));
        $this->addLine("hostname {$FQDN[0]}");
        $this->addLine("ip domain-name {$FQDN[1]}");
        $this->addUser($this->getOptVal('AdminUsername'), $this->getOptVal('AdminPassword'), 15);
        
        
        $Line = $this->addBlock("line con 0", ConfBlock::POS_LINE);
        $Line->addLine('logging synchronous');
        
    }
    
    public function enableSSH($Port = 22, $PrivateRangesOnly = true)
    {
        $this->addLine("!          ");
        $this->addLine("!    /\    ");
        $this->addLine("!   /  \   If your device doesn't have an RSA key yet, execute the following command:");
        $this->addLine("!  / !! \  crypto key generate rsa general-keys modulus 2048");
        $this->addLine("! <------>  ");
        $this->addLine("ip ssh version 2");
        if ($Port != 22 && $Port != 0) {
            $this->addLine("ip ssh port {$Port} rotary 1");
        }
        if ($PrivateRangesOnly) {
            $Block = $this->addBlock("ip access-list extended SSH", ConfBlock::POS_IPV4ACL);
            $Block->addLine(" permit tcp object-group PrivateRanges any eq 22");
            $Block->addLine(" permit tcp any any eq 8022");
        }
        $Block = $this->addBlock("line vty 0 15", ConfBlock::POS_LINE);
        $Block->addLine("logging synchronous");
        $Block->addLine("transport input ssh");
        if ($Port != 22 && $Port != 0) {
            $Block->addLine("rotary 1");
        }
        if ($PrivateRangesOnly) {
            $Block->addLine("access-class SSH in");
        }
    }


    /**
     * Parse a number format like this: 1,3,20-22. Strips out all characters
     * that are not numbers, comma's or dashes beforehand.
     * @param string $Nr
     * @return array Expanded form (1, 3, 20, 21, 22)
     */
    public function parseNrFormat($Nr) {
        $Nr = preg_replace('/[^0-9,-]*/', null, $Nr);
        $Arr = array();
        foreach(explode(',', $Nr) as $Item) {
            if(strstr($Item, '-')) {
                $Range = explode('-', $Item);
                $Arr = array_merge($Arr, range($Range[0], $Range[1]));
            } else {
                $Arr[] = $Item;
            }
        }
        return $Arr;
    }
}
