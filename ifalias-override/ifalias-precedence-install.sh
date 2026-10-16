# !/bin/bash
# Execute as librenms
#

install() {
   patch ~librenms/includes/custom/port-descr-parser-priority.inc.php < ifalias-override.patch && \
   lnms config:get port_descr_parser && \
   lnms config:set port_descr_parser includes/custom/port-descr-parser-priority.inc.php
}

uninstall() {
	rm ~librenms/includes/custom/port-descr-parser-priority.inc.php
	lnms config:get port_descr_parser 
	echo "Answer yes, you should reset to defaults"
        lnms config:set port_descr_parser 
}

ARG=$1

case $ARG in
	install) install;;
	uninstall) uninstall;;
	*) echo "usage: $0 <install|uninstall>"
esac











