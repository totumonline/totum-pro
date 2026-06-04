#!/bin/bash

echo "Performing Ubuntu version check..."
if [[ $(grep -c 'Ubuntu 26.04' /etc/issue) -ne 1 ]]; then
  echo "THIS SERVER IS NOT A UBUNTU 26.04 CHECK: sudo cat /etc/issue"
  exit 0
else
  echo "Ubuntu version is OK. Let's go..."
fi

if [[ $(sudo locale | grep -c 'LANG=en_US.UTF-8') -ne 1 ]]
then
  echo "- - - - - - - - - - - - - - - - - - - - - -"
  echo -e "\e[40;1;37mTHIS SERVER HAVE NOT \e[40;1;31men_US.UTF-8\e[40;1;37m LOCALE. YOU HAVE TO EXECUTE:"
  echo -e "sudo curl -O https://raw.githubusercontent.com/totumonline/totum-pro/pro/totum/moduls/install/setlocale.sh && sudo bash setlocale.sh" 
  echo -e "AND FOLLOW THE ON-SCREEN INSTRUCTIONS TO SETUP THE CORRECT LOCALE\033[0m"
  echo "- - - - - - - - - - - - - - - - - - - - - -"
  echo
  read -p "If you ready to go, type (A) we will download and run setlocale.sh: " TOTUMLOCALE
  echo
else
  TOTUMLOCALE="RUN"
fi

if [[ "$TOTUMLOCALE" == [Aa] ]]; then
    sudo curl -O https://raw.githubusercontent.com/totumonline/totum-pro/pro/totum/moduls/install/setlocale.sh && sudo bash setlocale.sh
    echo
    exit 0
elif [[ "$TOTUMLOCALE" == "RUN" ]]; then
    echo "Locale is OK. Let's go..."
    echo
else
    exit 0
fi

if [ -f "totum_install_vars" ]; then
  echo -e "\033[1mFile 'totum_install_vars' exists — continuing the installation...\033[0m"
  echo
else

echo -e "\e[40;1;37m                                                                         \033[0m"
echo -e "\e[40;1;37m                       ..       .*:-.                                    \033[0m"
echo -e "\e[40;1;37m                      -*:-.     -+*:.   .*:-.                            \033[0m"
echo -e "\e[40;1;37m                      -+*:-     -**:.   :+*-.                            \033[0m"
echo -e "\e[40;1;37m                      .++*-.    -*:-.   :**-.      ..-.                  \033[0m"
echo -e "\e[40;1;37m                       :+*:.    -**:.   **:-.     .:::-                  \033[0m"
echo -e "\e[40;1;37m                       .*+**.   :**:.  .++:-     ..***:                  \033[0m"
echo -e "\e[40;1;37m                        ***:-.  ::::.  -:::-    .:-::.                   \033[0m"
echo -e "\e[40;1;37m             ::--.      .*++::. :*::. -*::-.  .-*+**.                    \033[0m"
echo -e "\e[40;1;37m             -+:--.      .+:::-:::---:**+:. .::::::.                     \033[0m"
echo -e "\e[40;1;37m              **:-.       *+***::*::*:-::::::-+**-                       \033[0m"
echo -e "\e[40;1;37m              -+*:--.    .*::-------::::::::**+-                         \033[0m"
echo -e "\e[40;1;37m               -++:--------:-----------------::                          \033[0m"
echo -e "\e[40;1;37m                .++*::-----------------------::                          \033[0m"
echo -e "\e[40;1;37m                 .*++***:--------------------::                          \033[0m"
echo -e "\e[40;1;37m                   -+++++*::::::::**::::---:**:                          \033[0m"
echo -e "\e[40;1;37m                      -*+++++++++++*+++:::-:**.                          \033[0m"
echo -e "\e[40;1;37m                        .*+++++++++***+++****:                           \033[0m"
echo -e "\e[40;1;37m                           -++++++++***++++++.                           \033[0m"
echo -e "\e[40;1;37m                            *++++++*++++++++:                            \033[0m"
echo -e "\e[40;1;37m                            :++++++*++***+++-                            \033[0m"
echo -e "\e[40;1;37m                            -+*++++++++***+*:                            \033[0m"
echo -e "\e[40;1;37m                            -**+**+***+***+*:                            \033[0m"
echo -e "\e[40;1;37m                            -******::****:**:                            \033[0m"
echo -e "\e[40;1;37m                                                                         \033[0m"
echo -e "\033[43m\033[30m- - - - - - - - - - - - - - - - - - - - - - - - - - - - - - - - - - - - -\033[0m"
echo -e "\033[43m\033[30m                                                                         \033[0m"
echo -e "\033[43m\033[30m   TOTUM AUTOINSTALL SCRIPT                                              \033[0m"
echo -e "\033[43m\033[30m                                                                         \033[0m"
echo -e "\033[43m\033[30m   This install script will help you to install Totum online PRO.        \033[0m"
echo -e "\033[43m\033[30m                                                                         \033[0m"
echo -e "\033[43m\033[30m   \033[43m\033[31mONLY ON CLEAR!!! Ubuntu 26.04 \033[43m\033[30mwith or without SSL certificate.        \033[0m"
echo -e "\033[43m\033[30m                                                                         \033[0m"
echo -e "\033[43m\033[30m   For SSL you have to \033[43m\033[31mDELEGATE A VALID DOMAIN \033[43m\033[30mto this server.           \033[0m"
echo -e "\033[43m\033[30m                                                                         \033[0m"
echo -e "\033[43m\033[30m   If you not shure about you domain — cansel this install and check:    \033[0m"
echo -e "\033[43m\033[30m                                                                         \033[0m"
echo -e "\033[43m\033[31m   ping YOUR_DOMAIN                                                      \033[0m"
echo -e "\033[43m\033[30m                                                                         \033[0m"
echo -e "\033[43m\033[30m   To install without a domain, leave the domain field empty.            \033[0m"
echo -e "\033[43m\033[30m                                                                         \033[0m"
echo -e "\033[43m\033[30m   You will be able to add a domain later.                               \033[0m"
echo -e "\033[43m\033[30m                                                                         \033[0m"
echo -e "\033[43m\033[30m- - - - - - - - - - - - - - - - - - - - - - - - - - - - - - - - - - - - -\033[0m"
echo

read -p "If you ready to go, type (A) or cancel (Ctrl + C) and check you domain with ping: " TOTUMRUN
echo
if [[ "$TOTUMRUN" == [Aa] ]]; then
    echo "Started! Choose a number to select the timezone for your server..."
    echo
else
    exit 0
fi

TOTUMTIMEZONE=$(tzselect)

TOTUMBASEPASS=$(head /dev/urandom | tr -dc A-Za-z0-9 | head -c 24)

read -p "Enter your email: " CERTBOTEMAIL
echo
read -p "Create Totum superuser password: " TOTUMADMINPASS
echo
echo "- - - - - - - - - - - - - - - - - - - - - -"
echo "Enter domain without http/https delegated! to this server, like totum.online"
echo "If you want to install without a domain and certificates, leave it BLANK and press (ENTER)."
echo "You will be able to add a domain later."
echo "- - - - - - - - - - - - - - - - - - - - - -"
echo
read -p "Enter domain or leave this field empty: " CERTBOTDOMAIN

echo
echo "1) RU"
echo "2) EN"
echo "3) ES"
echo "4) DE"
echo

read -p "Select language: " TOTUMLANG
echo
if [[ $TOTUMLANG -eq 1 ]]
then
  TOTUMLANG=ru
elif [[ $TOTUMLANG -eq 2 ]]
then
  TOTUMLANG=en
elif [[ $TOTUMLANG -eq 3 ]]
then
  TOTUMLANG=es
elif [[ $TOTUMLANG -eq 4 ]]
then
  TOTUMLANG=de
else
  TOTUMLANG=ru
fi

echo
echo "- - - - - - - - - - - - - - - - - - - - - -"
echo
echo -e "\033[1mCheck you settings:\033[0m"
echo
echo -e "\033[1mTimezone:\033[0m " $TOTUMTIMEZONE
echo
echo -e "\033[1mEmail:\033[0m " $CERTBOTEMAIL
echo
echo -e "\033[1mPass for Totum admin:\033[0m " $TOTUMADMINPASS
echo
echo -e "\033[1mDomain:\033[0m " $CERTBOTDOMAIN
echo
echo -e "\033[1mLang:\033[0m " $TOTUMLANG
echo
echo "- - - - - - - - - - - - - - - - - - - - - - -"
echo

read -p "If you ready to install with this params type (A) or cancel (Ctrl + C): " TOTUMRUN2
echo

if [[ "$TOTUMRUN2" == [Aa] ]]; then
    echo "Start installation"
    echo
else
    exit 0
fi

echo "export TOTUMTIMEZONE=${TOTUMTIMEZONE}" >> totum_install_vars
echo "export TOTUMBASEPASS=${TOTUMBASEPASS}" >> totum_install_vars
echo "export CERTBOTEMAIL=${CERTBOTEMAIL}" >> totum_install_vars
echo "export TOTUMADMINPASS=${TOTUMADMINPASS}" >> totum_install_vars
echo "export CERTBOTDOMAIN=${CERTBOTDOMAIN}" >> totum_install_vars
echo "export TOTUMLANG=${TOTUMLANG}" >> totum_install_vars

echo "Environment variables written to totum_install_vars!"
echo
SKIP=1

fi

source totum_install_vars

if [[ $SKIP -eq 1 ]]
then

echo "- - - >"
echo

else

echo
echo "- - - - - - - - - - - - - - - - - - - - - -"
echo
echo -e "\033[1mTimezone:\033[0m " $TOTUMTIMEZONE
echo
echo -e "\033[1mEmail:\033[0m " $CERTBOTEMAIL
echo
echo -e "\033[1mPass for Totum admin:\033[0m " $TOTUMADMINPASS
echo
echo -e "\033[1mDomain:\033[0m " $CERTBOTDOMAIN
echo
echo -e "\033[1mLang:\033[0m " $TOTUMLANG
echo
echo "- - - - - - - - - - - - - - - - - - - - - - -"
echo

read -p "Continue with the installation or reconfiguration? Type (A) or (Ctrl + C): " CONTINUE
echo
  if [[ "$CONTINUE" == [Aa] ]]; then
    echo "Continuing the installation"
    echo
  else
    echo "Invalid input. Script aborted."
    echo
    exit 1
  fi
fi

if [ -z "$CERTBOTDOMAIN" ] && [ -f /home/totum/totum-mit/Conf.php ]; then
  echo "- - - - - - - - - - - - - - - - - - - - - - -"
  echo "Your installation is currently without a domain. Would you like to add a domain and SSL?"
  echo "- - - - - - - - - - - - - - - - - - - - - - -"
  echo
  read -p "Enter (A) to set it or (N) to proceed without changes: " CHANGE_D
  echo
  if [[ "$CHANGE_D" == [Aa] ]]; then

    read -p "Enter domain without http/https delegated! to this server like totum.online: " CERTBOTDOMAIN
    echo
    read -p "You have entered '$CERTBOTDOMAIN', to confirm enter (A) or (Ctrl + C) to abort: " CONFIRM_D
    echo
      if [[ "$CONFIRM_D" == [Aa] ]]; then

        sudo sed -i "s:export CERTBOTDOMAIN=:export CERTBOTDOMAIN=${CERTBOTDOMAIN}:g" totum_install_vars

        echo "$CERTBOTDOMAIN has been set to totum_install_vars."
        echo

      else

        echo "Invalid input. Script aborted."
        echo
        exit 1
      fi

  elif [[ "$CHANGE_D" == [Nn] ]]; then

    echo "No changes made. Proceed without domain."
    echo

  else

    echo "Invalid input. Script aborted."
    echo
    exit 1

  fi
fi

if ! command -v ansible >/dev/null 2>&1; then
  echo "apt install ansible"
  echo
  sudo apt update

  sudo apt install -y ansible

else
  echo "Ansible already installed..."
  echo
fi

if [ -f "ansible_totum_install.yml" ]; then
  echo -e "\033[1mAnsible playbook downloaded — continuing the installation...\033[0m"
  echo
else

sudo curl -O https://raw.githubusercontent.com/totumonline/totum-pro/pro/totum/moduls/install/ansible_totum_install.yml

fi

if [ -f "ansible_localhost" ]; then
  echo -e "\033[1mAnsible localhost settings exist — continuing the installation...\033[0m"
  echo
else

echo -e "[local]\nlocalhost ansible_connection=local" > ansible_localhost

fi

if [[ $EUID -eq 0 ]]
then
  ansible-playbook -i ansible_localhost ansible_totum_install.yml
  echo
else
echo "Enter the password for your user"
echo
ansible-playbook -i ansible_localhost --ask-become-pass ansible_totum_install.yml
echo
fi
