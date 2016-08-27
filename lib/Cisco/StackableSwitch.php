<?php
namespace Cisco;

/**
 * Description of Switch
 * TODO:
 * * Configure base interface (fastethernet, gigabitethernet, ...)
 * @author Glenn
 */
class StackableSwitch extends Common {
    public function __construct($Hostname = 'router1.lan.local')
    {
        parent::__construct($Hostname);
        $this->addOpt('PortBase', 'GigabitEthernet', 'select:GigabitEthernet,FastEthernet', 'GigabitEthernet, FastEthernet, etc...', 'Switch');
        $this->addOpt('StackNo', '1', 'int', 'Stack nr of this switch (default is 1 when unstacked)', 'Switch');
        $this->addOpt('NROfPorts', 28, 'int', 'Number of switchports', 'Switch');
        $this->addOpt('ManagementVLAN', 1, 'int');
        $this->addOpt('ManagementIP', '192.168.0.2', 'ip');
        $this->addOpt('ManagementMask', '255.255.255.0', 'netmask');
        $this->addOpt('DefaultGateway', '192.168.0.1', 'ip');
        $this->addOpt('VLANs', '1, 2', 'intarray', 'VLANs to create. Format: 1,2,10-20 (spaces allowed)', 'VLAN');
        $this->addOpt('AccessVLAN', 2, 'int', 'All access ports will be put in this VLAN', 'VLAN');
        $this->addOpt('TrunkPorts', 28, 'int', 'Ports to configure as trunk, format: 1,2,5-9 (spaces allowed)', 'VLAN');
        $this->addOpt('DHCPSnoopingTrustInterfaces', 28, 'int', 'Switchports to trust for DHCP, format: 1,2,5-9 (spaces allowed)', 'Protection');
        $this->addOpt('DisableVTP', true, 'bool');
        $this->addOpt('StormControlPPSLimit', 1000, 'int', 'Packets per second limit for storm-control. Shuts down interface when exceeded', 'Protection');
        $this->addOpt('EnableIPv6RAGuard', true, 'bool', "Enable IPv6 RAGuard and configure access ports to a Host profile (no RA's allowed)");
        // Override FQDNHostname here
        $this->addOpt('FQDNHostname', 'switch1.lan.local', 'string');
        
        $this->addOpt('AccessPortSTPProtection', 'bpduguard', 'select:bpduguard,bpdufilter', 'What to do when receiving STP PDUs. bpduguard: shutdown interface, bpdufilter: filter PDUs', 'Protection');
        $this->addOpt('IPDeviceTracking', true, 'bool', 'Enable IP device tracking on all ports', 'Protection');
        $this->addOpt('IPDeviceTrackingMax', 10, 'int', 'Maximum number of devices to track per port (range: 0 - 10)', 'Protection');
        $this->addOpt('IPDeviceTrackingProbeDelay', 10, 'int', 'Delay device tracking probe by this amount of seconds (range: 0 - 180)', 'Protection');
    }
    
    
    public function generate()
    {
        parent::generate();
        
        
        if($this->getOptVal('EnableSSH')) {
            $this->EnableSSH(22, false);
        }
        
        if($this->getOptVal('EnableIPv6RAGuard')) {
            $Block = $this->addBlock('ipv6 nd raguard policy Host', ConfBlock::POS_BEGIN, true);
        }
        
        
        $this->addLine('ip dhcp snooping vlan 1-4094');
        $this->addLine('ip dhcp snooping');
        $this->addLine('no ip dhcp snooping information option');
        
        
        if($this->getOptVal('DisableVTP')) {
            $this->addLine('vtp mode transparent');
        }
        
        
        if($this->getOptVal('IPDeviceTracking')) {
            $this->addLine('ip device tracking');
            $this->addLine("ip device tracking probe delay {$this->getOptVal('IPDeviceTrackingProbeDelay')}");
        }
        
        
        /* Ports */
        $i = 1;
        while($i <= $this->getOptVal('NROfPorts')) {
            $IntBlock = $this->addBlock("interface {$this->getOptVal('PortBase')} {$this->getOptVal('StackNo')}/0/{$i}", ConfBlock::POS_INT);
            if(in_array($i, $this->parseNrFormat($this->getOptVal('TrunkPorts')))) {
                $IntBlock->addLine('switchport trunk encapsulation dot1q');
                $IntBlock->addLine('switchport mode trunk');
                if($this->getOptVal('DisableVTP')) {
                    $IntBlock->addLine('no vtp');
                }
            } else {
                $IntBlock->addLine("switchport mode access");
                $IntBlock->addLine("switchport nonegotiate");
                $IntBlock->addLine('spanning-tree portfast');
                $IntBlock->addLine("switchport access vlan {$this->getOptVal("AccessVLAN")}");
                $IntBlock->addLine('no vtp');
                $IntBlock->addLine('ip verify source');
                if($this->getOptVal('EnableIPv6RAGuard')) {
                    $IntBlock->addLine('ipv6 nd raguard attach-policy Host');
                }
                $IntBlock->addLine("storm-control broadcast level pps {$this->getOptVal("StormControlPPSLimit")}");
                $IntBlock->addLine('storm-control action shutdown');
                if($this->getOptVal('AccessPortSTPProtection') == 'bpduguard') {
                    $IntBlock->addLine('spanning-tree bpduguard enable');
                } elseif ($this->getOptVal('AccessPortSTPProtection') == 'bpdufilter'){
                    $IntBlock->addLine('spanning-tree bpdufilter enable');
                }
            }

            if(in_array($i, $this->parseNrFormat($this->getOptVal('DHCPSnoopingTrustInterfaces')))) {
                $IntBlock->addLine('ip dhcp snooping trust');
            }
            
            if($this->getOptVal('IPDeviceTracking')) {
                $IntBlock->addLine("ip device tracking maximum {$this->getOptVal('IPDeviceTrackingMax')}");
            }
            
            $i++;
        }
        
        $IntBlock = $this->addBlock("interface vlan {$this->getOptVal('ManagementVLAN')}", ConfBlock::POS_INT);
        $IntBlock->addLine('no shutdown');
        $IntBlock->addLine("ip address {$this->getOptVal('ManagementIP')} {$this->getOptVal('ManagementMask')}");
        $IntBlock->addLine('no ip proxy-arp');
        
        
        $this->addBlock("ip default-gateway {$this->getOptVal('DefaultGateway')}", ConfBlock::POS_ROUTER);
        
        $Block = $this->addBlock("ntp server 194.50.97.12", ConfBlock::POS_NTP, true);
        $Block->addLine("ntp server 85.234.203.212");
    }
}
