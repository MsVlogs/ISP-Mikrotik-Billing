<?php
return ['profiles'=>[
 'v1600d_ep_series_v1_2'=>[
  'label'=>'VSOL/UPLINK EP Series / V1600D CLI v1.2',
  'models'=>['V1600D','V1600D Series','EP2','EP4','EP8'],'transport'=>'ssh',
  'commands'=>[
   'authorize_mac'=>['configure terminal','interface epon {{pon}}','onu-auth mode mac','onu mac-auth add {{mac}}','exit','exit'],
   'remove_mac'=>['configure terminal','interface epon {{pon}}','onu mac-auth del {{mac}}','exit','exit'],
   'disable_onu'=>['configure terminal','interface epon {{pon}}','onu {{onu}} disable','exit','exit'],
   'enable_onu'=>['configure terminal','interface epon {{pon}}','onu {{onu}} enable','exit','exit'],
   'pppoe'=>['configure terminal','interface epon {{pon}}','onu {{onu}} pri wan_conn add route internet nat enable','onu {{onu}} pri wan_conn index 1 pppoe proxy disable user {{username}} pwd {{password}} server {{server}} mode auto','onu {{onu}} pri wan_conn commit','exit','exit'],
   'static_ip'=>['configure terminal','interface epon {{pon}}','onu {{onu}} pri wan_conn add route internet nat enable','onu {{onu}} pri wan_conn index 1 static ip {{ip}} mask {{netmask}} gw {{gateway}} dns master {{dns1}} slave {{dns2}}','onu {{onu}} pri wan_conn commit','exit','exit'],
   'save'=>['write'],
  ],
 ],
]];