<?php
namespace Cisco;

/**
 * Standalone access point
 * TODO: 5 GHz support
 * @author Glenn
 */
class StandaloneAP extends Common {
    
    public function __construct($Hostname = 'router1.lan.local')
    {
        parent::__construct($Hostname);
        
        $SSIDOpts[] = new ConfigOption('VLAN', '1', 'int', "", null, 5);
        $SSIDOpts[] = new ConfigOption('SSID', 'Pretty fly for a Wi-Fi', 'string');
        $SSIDOpts[] = new ConfigOption('PSK', 'wireless', 'string', 'WPA2-PSK');
        
        
        $this->addOpt('SSIDList', $SSIDOpts, 'listOfListOfOpts',
            "List of SSIDs. A single SSID will be directly bridged with the LAN interface (no VLAN tagging). Multiple SSID's will all be tagged to the LAN interface.");
        
        $this->addOpt('LANInterface', 'GigabitEthernet0', 'string', "LAN interface");


        $SNTPOpts[] = new ConfigOption('IP', '193.104.37.238', 'string');

        $this->addOpt('SNTPServers', $SNTPOpts, 'listOfListOfOpts');


        // Overrides
        $this->setOptDefaultValue('FQDNHostname', 'ap1.lan.local');
    }
    
    
    public function generate()
    {
        parent::generate();

        
        if($this->getOptVal('EnableSSH')) {
            $this->EnableSSH(22, false);
        }
        
        $this->addLine('no enable secret');
        $this->addLine('no username Cisco');
        
        
        $NrOfSSID = count($this->getOptVal('SSIDList')['VLAN']);
        
        $RadioMainInt = $this->addBlock("interface Dot11Radio0", ConfBlock::POS_INT);
        $RadioMainInt->addLine('no ip proxy-arp');
        
        if($NrOfSSID > 1) {
            $RadioMainInt->addLine('mbssid');
        }
        
        $i = 0;
        while($i < $NrOfSSID) {
            $SSID = trim($this->getOptVal('SSIDList')['SSID'][$i]);
            $Block = $this->addBlock("dot11 ssid {$SSID}", ConfBlock::POS_DOT11);
            $Block->addLine('authentication open');
            $Block->addLine('authentication key-management wpa version 2');
            $Block->addLine("wpa-psk ascii 0 {$this->getOptVal('SSIDList')['PSK'][$i]}");
            
            if($NrOfSSID > 1) {
                $Block->addLine('mbssid guest-mode');
                $VLANNr = trim($this->getOptVal('SSIDList')['VLAN'][$i]);
                $Block->addLine('vlan '. $VLANNr);
                $RadioMainInt->addLine("encryption vlan {$VLANNr} mode ciphers aes-ccm");
                $RadioMainInt->addLine("ssid {$SSID}");
                $RadioSubInt = $this->addBlock("interface Dot11Radio0.{$VLANNr}", ConfBlock::POS_INT);
                $RadioSubInt->addLine('no ip proxy-arp');
                $RadioSubInt->addLine("encapsulation dot1Q {$VLANNr}");
                $RadioSubInt->addLine("bridge-group {$VLANNr}");

                $LANSubInt = $this->addBlock("interface {$this->getOptVal('LANInterface')}.{$VLANNr}", ConfBlock::POS_INT);
                $LANSubInt->addLine("encapsulation dot1Q {$VLANNr}");
                $LANSubInt->addLine("bridge-group {$VLANNr}");
                $LANSubInt->addLine('no ip proxy-arp');

                $this->addLine("bridge {$VLANNr} protocol ieee");
                $this->addLine("bridge {$VLANNr} route ip");
                
                
            } else {
                $Block->addLine('guest-mode');
                $RadioMainInt->addLine("encryption mode ciphers aes-ccm");
                $RadioMainInt->addLine("ssid {$SSID}");
                $RadioMainInt->addLine("bridge-group 1");

                $this->addLine("bridge 1 protocol ieee");
                $this->addLine("bridge 1 route ip");
                
            }
            $i++;
        }
        
        $RadioMainInt->addLine('no shutdown');


        $IntBlock = $this->addBlock('interface BVI1', ConfBlock::POS_INT);
        $IntBlock->addLine('ip address dhcp');
        $IntBlock->addLine('no ip proxy-arp');

        $NTPBlock = null;

        if(count($this->getOptVal('SNTPServers')['IP']) > 0)
        {
            foreach ($this->getOptVal('SNTPServers')['IP'] as $ntpServer)
            {
                if($ntpServer === '')
                {
                    continue;
                }
                if($NTPBlock === null)
                {
                    $NTPBlock = $this->addBlock("sntp server {$ntpServer}", ConfBlock::POS_NTP, true);
                }
                else
                {
                    $NTPBlock->addLine("sntp server {$ntpServer}");
                }
            }
        }

    }
    
    public function validateOpts()
    {
        $Errors = array();
        /*$SSIDCount = count(explode(',', $this->getOptVal('SSIDList')));
        $PSKCount = count(explode(',', $this->getOptVal('PSKList')));
        $VLANCount = count(explode(',', $this->getOptVal('VLANCount')));
        
        if($SSIDCount != $PSKCount) {
            $Errors[] = "The number of PSK's doesn't match the number of SSID's";
        }
        if($SSIDCount != $VLANCount) {
            $Errors[] = "The number of VLAN's doesn't match the number of SSID's";
        }*/
        
        return $Errors;
    }
    
}
