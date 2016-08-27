<?php
namespace Cisco;

class BasicRouter extends CommonRouter {
    public function __construct($Hostname = 'router1') {
        parent::__construct($Hostname);

        $this->addOpt('WANInterface', 'GigabitEthernet0/1');
        $this->addOpt('LANInterface', 'GigabitEthernet0/0', 'string', "Ignored when using the 'vlan' VLAN style");
        $this->addOpt('VLANStyle', 'subinterface', 'select:subinterface,vlan', 'subinterface: Gig0/0.5, Gig0/0.6, etc... (base interface taken from LANInterface).<br />vlan: Vlan5, Vlan6, etc...');
        
        // $this->addOpt('DHCPOnVLAN', 'all', 'text', 'Set to 0 to disable DHCP on all VLANs, set to "all" to enable DHCP on all VLANs. Format: 1,2,10-20');
        
        // $this->addOpt('NTPServers', '194.50.97.12, 85.234.203.212', 'iparray');
        
    }
    
    
    public function generate() {
       
        parent::generate();
        
        
        if($this->getOptVal('DNSServer') == true) {
            $this->addLine('ip dns server');
        }
        
        // Interfaces
        
        if($this->getOptVal('VLANStyle') == 'subinterface') {
            // Enable the main interface where subinterfaces will be attached to
            $IntBlock = $this->addBlock("interface {$this->getOptVal('LANInterface')}", ConfBlock::POS_INT);
            $IntBlock->addLine('no ip proxy-arp');
            $IntBlock->addLine('no shutdown');
            $IntBlock->addLine("no ip unreachables");
            $IntBlock->addLine("no ip redirects");
            $IntBlock->addLine("no mop enabled");
        }
        
        
        if($this->getOptVal('sVPN')) {
            $this->addSimpleVPN($this->getOptVal('WANInterface'));
        }

        // WAN interface
        
        $Block = $this->addBlock("interface {$this->getOptVal('WANInterface')}", ConfBlock::POS_INT);
        
        $Block->addLine("ip address dhcp");
        $Block->addLine("ip nat outside");
        $Block->addLine("ip access-group Internet-IN in");
        $Block->addLine("service-policy output WAN-Output");
        $Block->addLine("no shutdown");
        $Block->addLine("no ip proxy-arp");
        $Block->addLine("no ip redirects");
        $Block->addLine("no ip unreachables");
        $Block->addLine("no cdp enable");
        $Block->addLine("no mop enabled");

        // NAT
        
        $this->addBlock("ip nat inside source list NAT interface {$this->getOptVal('WANInterface')} overload", ConfBlock::POS_NAT, true);
        

    }
}
