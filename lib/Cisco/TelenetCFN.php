<?php
namespace Cisco;

class TelenetCFN extends CommonRouter implements \IConfig {
    public function __construct($Hostname = 'router1')
    {
        parent::__construct($Hostname);
        
        $this->addOpt('BGPASN', 65000, 'int', "This router's BGP AS number", 'Telenet Corporate Fibernet');
        $this->addOpt('PublicLANRange', '192.0.2.80/29', 'prefix', 'The Telenet public IP address range. Enter in CIDR format.', 'Telenet Corporate Fibernet');
        $this->addOpt('COAXGateway', '198.51.100.1', 'ip', '', 'Telenet Corporate Fibernet');
        $this->addOpt('COAXIP', '198.51.100.2', 'ip', '', 'Telenet Corporate Fibernet');
        $this->addOpt('VDSLGateway', '213.224.8.1', 'ip', '', 'Telenet Corporate Fibernet');
        $this->addOpt('TelenetASN', 6848, 'int', '', 'Telenet Corporate Fibernet');
        $this->addOpt('BGPVDSLNeighbor', '213.224.8.1', 'ip', '', 'Telenet Corporate Fibernet');
        $this->addOpt('BGPCOAXNeighbor', '198.51.100.1', 'ip', '', 'Telenet Corporate Fibernet');
        $this->addOpt('VDSLPPPOEUsername', 'x', 'string', '', 'Telenet Corporate Fibernet');
        $this->addOpt('VDSLPPPOEPassword', 'y', 'string', '', 'Telenet Corporate Fibernet');
        
        $this->addOpt('COAXInterface', 'GigabitEthernet0/1', 'string', 'Interface that connects to the cable modem', 'Telenet Corporate Fibernet');
        $this->addOpt('VDSLInterface', 'GigabitEthernet0/0', 'string', 'Interface that connects to the VDSL modem', 'Telenet Corporate Fibernet');
    }
    
    
    public function validateOpts()
    {
        
    }
    

    public function generate()
    {
        $this->addOpt('VLANStyle', 'vlan');
        $this->setOptVal('VLANStyle', 'vlan');
        parent::generate();

        
        $IP = $this->splitIP($this->getOptVal('PublicLANRange'));
        $SM = $this->convertCIDRToSubnetMask($IP['prefix']);
        

        $Block = $this->addBlock("interface Loopback 0", ConfBlock::POS_INT);
        $Block->addLine("ip address {$this->nextIP($IP['ip'])} 255.255.255.255");
        
        
        if($this->getOptVal('sVPN')) {
            $this->addSimpleVPN('loopback0');
        }

        
        
        $Block = $this->addBlock("interface {$this->getOptVal('COAXInterface')}", ConfBlock::POS_INT);
        
        $Block->addLine("description * COAX");
        $Block->addLine("ip address {$this->getOptVal('COAXIP')} 255.255.255.252");
        $Block->addLine("ip access-group Internet-IN in");
        $Block->addLine("service-policy output WAN-Output");
        $Block->addLine("no shutdown");
        $Block->addLine("no ip proxy-arp");
        $Block->addLine("no cdp enable");
        $Block->addLine("ip nat outside");
        $Block->addLine("no ip redirects");
        $Block->addLine("no ip unreachables");
        $Block->addLine("no mop enabled");

        $Block = $this->addBlock("interface {$this->getOptVal('VDSLInterface')}", ConfBlock::POS_INT);
        
        $Block->addLine("description * VDSL");
        $Block->addLine("no ip redirects");
        $Block->addLine("no ip unreachables");
        $Block->addLine("no ip address");
        $Block->addLine("no shutdown");
        $Block->addLine("no ip proxy-arp");
        $Block->addLine("no cdp enable");
        $Block->addLine("pppoe enable group global");
        $Block->addLine("pppoe-client dial-pool-number 1");
        $Block->addLine("no mop enabled");
        
        $Block = $this->addBlock("interface Dialer1", ConfBlock::POS_INT);
        $Block->addLine("ip access-group Internet-IN in");
        $Block->addLine("ip address negotiated");
        $Block->addLine("ip mtu 1492");
        $Block->addLine("ip nat outside");
        $Block->addLine("ip virtual-reassembly in");
        $Block->addLine("encapsulation ppp");
        $Block->addLine("ip tcp adjust-mss 1452");
        $Block->addLine("dialer pool 1");
        $Block->addLine("dialer-group 1");
        $Block->addLine("ppp authentication chap callin");
        $Block->addLine("ppp chap hostname {$this->getOptVal('VDSLPPPOEUsername')}");
        $Block->addLine("ppp chap password 0 {$this->getOptVal('VDSLPPPOEPassword')}");
        $Block->addLine("no ip redirects");
        $Block->addLine("no ip unreachables");
        


        
        $Block = $this->addBlock("router bgp {$this->getOptVal('BGPASN')}", ConfBlock::POS_ROUTER);
        $Block->addLine("timers bgp 5 20");
        $Block->addLine("neighbor {$this->getOptVal('BGPCOAXNeighbor')} remote-as {$this->getOptVal('TelenetASN')}");
        $Block->addLine("neighbor {$this->getOptVal('BGPVDSLNeighbor')} remote-as {$this->getOptVal('TelenetASN')}");
        $Block->addLine("neighbor {$this->getOptVal('BGPVDSLNeighbor')} ebgp-multihop 2");
        $Block->addLine($Block = $this->newBlock("address-family ipv4"));
        
        $IP = $this->splitIP($this->getOptVal('PublicLANRange'));
        $SM = $this->convertCIDRToSubnetMask($IP['prefix']);
        
        
        $Block->addLine("aggregate-address {$IP['ip']} {$SM}");
        $Block->addLine("redistribute connected route-map BGP-REDIST-PUBLIC");
        $Block->addLine("neighbor {$this->getOptVal('BGPCOAXNeighbor')} activate");
        $Block->addLine("neighbor {$this->getOptVal('BGPCOAXNeighbor')} prefix-list BGP-OUT out");
        $Block->addLine("neighbor {$this->getOptVal('BGPCOAXNeighbor')} route-map BGP-COAX-IN in");
        $Block->addLine("neighbor {$this->getOptVal('BGPVDSLNeighbor')} activate");
        $Block->addLine("neighbor {$this->getOptVal('BGPVDSLNeighbor')} prefix-list BGP-OUT out");
        $Block->addLine("neighbor {$this->getOptVal('BGPVDSLNeighbor')} route-map BGP-VDSL-IN in");
        
        
        $Block = $this->addBlock("route-map BGP-OUT permit 10", ConfBlock::POS_ROUTEMAP);
        $Block->addLine("match ip address prefix-list TELENET-PUBLIC");
        
        
        $Block = $this->addBlock("route-map BGP-COAX-IN permit 10", ConfBlock::POS_ROUTEMAP);
        $Block->addLine("set local-preference 100");

        $Block = $this->addBlock("route-map BGP-VDSL-IN permit 10", ConfBlock::POS_ROUTEMAP);
        $Block->addLine("set local-preference 80");
        
        $this->addLine("ip prefix-list TELENET-PUBLIC seq 5 permit {$this->getOptVal('PublicLANRange')}");
        
        $this->addBlock("ip route {$this->getOptVal('BGPVDSLNeighbor')} 255.255.255.255 Dialer1", ConfBlock::POS_IPV4ROUTE, true);


        
        $this->addBlock("ip nat inside source list NAT interface loopback0 overload", ConfBlock::POS_NAT, true);

    }
}
