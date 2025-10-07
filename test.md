
:if ([:len [/interface wireguard find where name="wg_trilink_cloudtik_wg"]] = 0) do={
    /interface wireguard add mtu=1420 name="wg_trilink_cloudtik_wg"
}

:if ([:len [/ip address find where address~"192.168.14.3/24"]] = 0) do={
    /ip address add address="192.168.14.3/24" interface="wg_trilink_cloudtik_wg" network="192.168.14.0"
}

:if ([:len [/interface wireguard find where name="wg_trilink_cloudtik_wg"]] = 0) do={
/interface wireguard add mtu=1420 name="wg_trilink_cloudtik_wg"
}

:if ([:len [/ip address find where address~"192.168.14.3/24"]] = 0) do={
    /ip address add address="192.168.14.3/24" interface="wg_trilink_cloudtik_wg" network="192.168.14.0"
}

:if ([:len [/interface wireguard peers find where endpoint-address="secure.cloudtik.net"]] = 0) do={
    /interface wireguard peers add \
        allowed-address="192.168.14.1/24" \
        endpoint-address="secure.cloudtik.net" \
        endpoint-port=61670 \
        interface="wg_trilink_cloudtik_wg" \
        persistent-keepalive=1m \
        public-key="4eghJTW/nJSy4W8HONve2fQihX/07M+ZXdlLWiwM2Xw="
}

:local wgPubKey [/interface wireguard get [find name="wg_trilink_cloudtik_wg"] value-name=public-key]


:put "
==================== WIREGUARD SETUP COMPLETED ===================="
:put "Interface: wg_trilink_cloudtik_wg"
:put "Local IP: 192.168.14.3/24"
:put "Peer Endpoint: secure.cloudtik.net:61670"
:put "Peer Allowed Address: 192.168.14.1/24"
:put ("Local Public Key: " . [/interface wireguard get [find name="wg_trilink_cloudtik_wg"] value-name=public-key])
:put "===================================================================="
:put "
COPY THE CONFIGURATION ABOVE AND USE IT TO UPDATE OTHER ROUTERS IF NEEDED
"