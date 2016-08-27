<?php
namespace Cisco;

/**
 * Description of Cisco_DMVPN
 *
 * @author Glenn
 */
class DMVPN extends Config {
    
    public function __construct()
    {
        parent::__construct();
        $this->addOpt('ISAKMPKey', 'xxxxxxxx', 'text', 'ISAKMP (Phase I) key');
        $this->addOpt('ID', 250, 'int', 'Will be used to set interface number, network-id, etc...');
        $this->addOpt('NHRPAuthKey', 'xxxxxxxx', 'text', 'NHRP authentication key');
    }
    
    
    
    public function generate()
    {
        $this->encryption($this->getOptVal('ISAKMPKey'));

        $this->hub(
                $this->getOptVal('ID'),
                '192.168.0.1',
                $this->getOptVal('ID'),
                $this->getOptVal('NHRPAuthKey'),
                'DMVPN',
                'GigabitEthernet0/1'
        );
        
        $this->spoke(
                $this->getOptVal('ID'),
                '192.168.0.1',
                $this->getOptVal('ID'),
                $this->getOptVal('NHRPAuthKey'),
                'DMVPN',
                'GigabitEthernet0/1',
                array('192.168.0.1'),
                array('1.2.3.4')
        );
    }
    
    
    private function encryption($ISAKMPKey) {
        $Block = $this->addBlock("crypto isakmp policy 10", ConfBlock::POS_ISAKMP);
        $Block->addLine("authentication pre-share");
        
        $Block = $this->addBlock("crypto ipsec transform-set AES-SHA esp-aes 256 esp-sha-hmac", ConfBlock::POS_CRYPTIPSECTS);
        $Block->addLine("mode transport");
        
        $Block = $this->addBlock("crypto ipsec profile DMVPN", ConfBlock::POS_CRYPTIPSECPROF);
        $Block->addLine("set transform-set AES-SHA");
        
        $this->addLine("crypto isakmp key {$ISAKMPKey} address 0.0.0.0 0.0.0.0");
    }
    
    
    private function spoke(
        $TunnelId, $TunnelIP, $NHRPNetworkId, $NHRPAuthKey, $IpsecProfileName,
            $SourceInterface, $HubIPs, $HubPublicIPs)
    {
        $Block = $this->addBlock("interface Tunnel {$TunnelId}", ConfBlock::POS_INT);
        $Block->addLine("! *** Apply this tunnel to the SPOKE ***");
        $this->tunnelCommon($Block);
        $Block->addLine("ip address {$TunnelIP}");
        $Block->addLine("ip nhrp shortcut");
        $Block->addLine("ip nhrp authentication {$NHRPAuthKey}");
        
        foreach($HubIPs as $Index => $IP) {
            $Block->addLine("ip nhrp map multicast {$IP}");
            $Block->addLine("ip nhrp map {$IP} {$HubPublicIPs[$Index]}");
        }

        $Block->addLine("ip nhrp network-id {$NHRPNetworkId}");
        $Block->addLine("ip nhrp registration no-unique");
        $Block->addLine("tunnel source {$SourceInterface}");
        $Block->addLine("tunnel protection ipsec profile {$IpsecProfileName}");
        return $Block;
    }
    
    private function hub(
        $TunnelId, $TunnelIP, $NHRPNetworkId, $NHRPAuthKey, $IpsecProfileName, 
            $SourceInterface
        )
    {
        $Block = $this->addBlock("interface Tunnel {$TunnelId}", ConfBlock::POS_INT);
        $Block->addLine("! *** Apply this tunnel to the HUB ***");
        $this->tunnelCommon($Block);
        $Block->addLine("ip address {$TunnelIP} 255.255.255.0");
        $Block->addLine("ip nhrp authentication {$NHRPAuthKey}");
        $Block->addLine("ip nhrp map multicast dynamic");
        $Block->addLine("ip nhrp network-id {$NHRPNetworkId}");
        $Block->addLine("tunnel source {$SourceInterface}");
        $Block->addLine("tunnel protection ipsec profile {$IpsecProfileName}");
        return $Block;
    }
    
    private function tunnelCommon(&$Block) {
        $Block->addLine("ip nhrp holdtime 300");
        $Block->addLine("ip tcp adjust-mss 1352");
        $Block->addLine("ip mtu 1392");
        $Block->addLine("ip ospf network point-to-multipoint");
        $Block->addLine("tunnel mode gre multipoint");
        $Block->addLine("ip nhrp redirect");
    }

}
