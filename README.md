# Totum PRO

## The PRO version is distributed under a limited license

For PRO license terms, see the [PRO version page](https://totum.online/pro)

The license text is available in the [totum-pro-license repository](https://github.com/totumonline/totum-pro-license/blob/main/license_last)

## Installing V5 PRO

> System for initial installation: **Ubuntu 24.04**

> V5 uses web-sockets, so if you use intermediate proxies, you need to configure them in advance. To ensure WebSocket requests are not lost when passing through an intermediate proxy server before Nginx, make sure this proxy correctly handles and forwards the Upgrade and Connection headers used to establish WebSocket connections.


Run the standard Totum installer (by default, it is assumed to be `/root`) and select the PRO installation option:

```
sudo curl -O https://raw.githubusercontent.com/totumonline/totum-mit/master/totum/moduls/install/totum_autoinstall.sh && sudo bash totum_autoinstall.sh
```

## Switching from Totum MIT V5

> This instruction assumes that TOTUM MIT V5 was installed using the installation script.

> There are no mechanisms to revert from PRO to MIT.

> Solutions developed in MIT are compatible with PRO.

> Solutions developed in PRO are not backward compatible with MIT.

> V5 uses web-sockets, so if you use intermediate proxies, you need to configure them in advance. To ensure WebSocket requests are not lost when passing through an intermediate proxy server before Nginx, make sure this proxy correctly handles and forwards the Upgrade and Connection headers used to establish WebSocket connections.


Run the standard Totum installer from the same folder from which it was run on the server during the initial system installation:

```
sudo curl -O https://raw.githubusercontent.com/totumonline/totum-mit/master/totum/moduls/install/totum_autoinstall.sh && sudo bash totum_autoinstall.sh
```

The installer will offer you to change the installation option to PRO.

If you forgot the folder from which the installer was run, search for `totum_install_vars` (this file is created during installation):

```
sudo find / -name "totum_install_vars"
```

## Switching from Totum MIT V4

See the [MIT V4 - PRO V5 upgrade instructions](https://docs.totum.online/update_5_mit_pro)
