<?php
namespace Cisco;

class CommonRouter extends Common {
    
    
    public function __construct($Hostname)
    {
        parent::__construct($Hostname);
        $this->addOpt('sVPN', true, 'bool', 'Enable simple PPTP VPN', 'Simple VPN');
        $this->addOpt('sVPNUseRadius', false, 'bool', 'Use RADIUS for authenticating and authorizing PPTP VPN connections', 'Simple VPN');
        $this->addOpt('sVPNRadiusServerIP', '192.168.1.1', 'ip', '', 'Simple VPN');
        $this->addOpt('sVPNRadiusServerName', 'rrad1', 'string', '', 'Simple VPN');
        $this->addOpt('sVPNRadiusServerKey', 'xxxxxxxx', 'string', '', 'Simple VPN');
        $this->addOpt('DNSServer', false, 'bool', 'Enable the DNS server. Will also set the router as DNS server in DHCP scopes');
        $this->addOpt('VLANs', '1, 2', 'intarray', 'VLANs to create. Format: 1,2,10-20 (spaces allowed)', 'VLAN');
        $this->addOpt('VLANIPs', '10.1.v.1/24', 'text', 'IP address for the VLANs. Format: 192.0.2.1/24. v will be replaced by the VLAN number', 'VLAN');
        $this->addOpt('DHCPOnVLAN', 'all', 'text', 'Set to 0 to disable DHCP on all VLANs, set to "all" to enable DHCP on all VLANs. Format: 1,2,10-20', 'VLAN');
        $this->addOpt('GuestVLAN', 0, 'int', 'Set to 0 to not create a guest VLAN', 'VLAN');
        $this->addOpt('InternetUpload', 8000, 'int', 'For Quality of Service, shape upload traffic to this amount, in kilobytes per second.', 'Quality of Service');
        
        $this->addOpt('SSHAltPort', 8022, 'int', 'Make SSH also listen on this port. Set to 0 to leave default (22)');
        $this->addOpt('SSHTo22PrivateOnly', true, 'bool', 'Only allow SSH connections to port 22 from private IP ranges');
        
    }



    public function addBasicQoS()
    {
        $Block = $this->addBlock("class-map match-any VoIP", ConfBlock::POS_POLMAP);
        $Block->addLine("match protocol sip");
        $Block->addLine("match protocol rtp audio");
        
        $Block = $this->addBlock("policy-map Internet-Output", ConfBlock::POS_POLMAP);
        $Block->addLine($Class = $this->newBlock("class VoIP"));
        $Class->addLine("priority 512");
        $Block->addLine($Class = $this->newBlock("class class-default"));
        $Class->addLine("fair-queue");
        
        $Block = $this->addBlock("policy-map WAN-Output", ConfBlock::POS_POLMAP);
        $Block->addLine($Class = $this->newBlock("class class-default"));
        $Class->addLine($Shape = $this->newBlock("shape average " . $this->getOptVal('InternetUpload') * 1000));
        $Shape->addLine("service-policy Internet-Output");
    }

    
    public function generate()
    {
        parent::generate();
        
        $this->addLine("no ip bootp server");
        
        if($this->getOptVal('EnableSSH')) {
            $this->EnableSSH($this->getOptVal('SSHAltPort'), $this->getOptVal('SSHTo22PrivateOnly'));
        }
        
        
        $this->addBasicQoS();
        
        $Block = $this->addBlock("ip access-list extended NAT", ConfBlock::POS_IPV4ACL);
        $Block->addLine("deny ip any object-group PrivateRanges");
        $Block->addLine("permit ip any any");
        
        $Block = $this->addBlock("ntp server 194.50.97.12", ConfBlock::POS_NTP, true);
        $Block->addLine("ntp server 85.234.203.212");

        $Block = $this->addBlock("object-group network PrivateRanges", ConfBlock::POS_OBJGROUP);
        $Block->addLine("192.168.0.0 255.255.0.0");
        $Block->addLine("172.16.0.0 255.240.0.0");
        $Block->addLine("10.0.0.0 255.0.0.0");
        
        
        $Block = $this->addBlock("ip access-list extended Internet-IN", ConfBlock::POS_IPV4ACL);
        $Block->addLine("deny udp any any eq 53");
        $Block->addLine("deny tcp any any eq 53");
        $Block->addLine("permit ip any any");
        
        
        $FQDN = $this->parseFQDN($this->getOptVal('FQDNHostname'));

        foreach($this->parseNrFormat($this->getOptVal('VLANs')) as $VLANNr) {
            
            if($this->getOptVal('VLANStyle') == 'subinterface') {
                $IntBlock = $this->addBlock("interface {$this->getOptVal('LANInterface')}.{$VLANNr}", ConfBlock::POS_INT);
                $IntBlock->addLine("encapsulation dot1Q {$VLANNr}");
            } else {
                $this->addBlock("vlan {$VLANNr}", ConfBlock::POS_VLAN);
                $IntBlock = $this->addBlock("interface Vlan {$VLANNr}", ConfBlock::POS_INT);
                
            }
            $IntBlock->addLine('no ip proxy-arp');
            $IntBlock->addLine("no ip redirects");
            $IntBlock->addLine('ip nat inside');
            $IntBlock->addLine("no ip unreachables");
            
            $VLANIP = $this->splitIP(str_replace('v', $VLANNr, $this->getOptVal('VLANIPs')));
            
            $NATACL[] = array(
                $this->getNetAddrFromIP($VLANIP['ip'], $VLANIP['prefix']),
                $this->convertCIDRToSubnetMask($VLANIP['prefix'], true)
            );
            
            $IntBlock->addLine("ip address {$VLANIP['ip']} {$this->convertCIDRToSubnetMask($VLANIP['prefix'])}");
            $IntBlock->addLine('no shutdown');
            
            if($this->getOptVal('DHCPOnVLAN') == 'all' || in_array($VLANNr, $this->parseNrFormat($this->getOptVal('DHCPOnVLAN')))) {
                $DHCPBlock = $this->addBlock("ip dhcp pool {$this->getNetAddrFromIP($VLANIP['ip'], $VLANIP['prefix'])}/{$VLANIP['prefix']}", ConfBlock::POS_DHCP);
                $DHCPBlock->addLine("network {$this->getNetAddrFromIP($VLANIP['ip'], $VLANIP['prefix'])} /{$VLANIP['prefix']}");
                $DHCPBlock->addLine("default-router {$VLANIP['ip']}");
                $DHCPBlock->addLine("domain-name {$FQDN[1]}");
                if($this->getOptVal('DNSServer')) {
                    $DHCPBlock->addLine("dns-server {$VLANIP['ip']}");
                } else {
                    $DHCPBlock->addLine("dns-server 195.130.131.139 195.130.130.139 195.130.131.11 195.130.130.11");
                }
            }
            
            if($this->getOptVal('GuestVLAN') == $VLANNr) {
                $Block = $this->addBlock('ip access-list extended Guest-IN', ConfBlock::POS_IPV4ACL);
                $Block->addLine("permit ip any host {$VLANIP['ip']}");
                $Block->addLine('deny ip any object-group PrivateRanges');
                $Block->addLine('permit ip any any');
                $IntBlock->addLine('ip access-group Guest-IN in');
            }


        }
        
        
    }
    
    public function addSimpleVPN($SourceInterface)
    {
        if($this->getOptVal('sVPNUseRadius')) {
            $Block = $this->addBlock("aaa group server radius PPTPVPNRadius", ConfBlock::POS_AAA);
            $Block->addLine("server name {$this->getOptVal('sVPNRadiusServerName')}");
            
            $Block = $this->addBlock("aaa authentication ppp PPTPVPN group PPTPVPNRadius", ConfBlock::POS_AAA, true);
            $Block->addLine("aaa authorization network PPTPVPN group PPTPVPNRadius");
            $Block = $this->addBlock("radius server {$this->getOptVal('sVPNRadiusServerName')}", ConfBlock::POS_RADIUS);
            $Block->addLine("address ipv4 {$this->getOptVal('sVPNRadiusServerIP')}");
            $Block->addLine("key {$this->getOptVal('sVPNRadiusServerKey')}");
        }


        $this->addLine('ip local pool VPN 10.1.254.100 10.1.254.110');
        $Block = $this->addBlock("vpdn enable", ConfBlock::POS_VPDN, true);
        $Block->addLine("vpdn logging");
        $Block->addLine("vpdn logging local");
        $Block->addLine("vpdn logging user");
        $Block = $this->addBlock("vpdn-group 1", ConfBlock::POS_VPDN);
        $Block->addLine($Dialin = $this->newBlock("accept-dialin"));
        $Dialin->addLine("protocol pptp");
        $Dialin->addLine("virtual-template 1");

        $Block = $this->addBlock("interface Virtual-Template1", ConfBlock::POS_INT);
        $Block->addLine("ip unnumbered {$SourceInterface}");
        if($this->getOptVal('sVPNUseRadius')) {
            $Block->addLine("ppp authentication ms-chap-v2 PPTPVPNRadius");
            $Block->addLine("ppp authorization PPTPVPNRadius");
        } else {
            $Block->addLine("ppp authentication ms-chap-v2");
        }
        $Block->addLine("ip nat inside");
        $Block->addLine("ppp encrypt mppe auto");
        $Block->addLine("peer default ip address pool VPN");
    }

}


/*
 !
ip dns view WH
 dns forwarder 192.168.8.2
 dns forwarder 192.168.8.3
ip dns view default
 dns forwarder 195.238.2.21
 dns forwarder 195.238.2.22
ip dns view-list WH
 view WH 1
  restrict name-group 1
 view default 20
ip dns name-list 1 permit .*WH\.LOCAL
ip dns server view-group WH
ip dns server

 */