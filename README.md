# Totum PRO

**Initially installs [totum-MIT](https://github.com/totumonline/totum-mit) and then it switches to the PRO version!**

If you want this instruction in Russian go to the: https://ru.docs.totum.online/pro-install

## How to switch the MIT version to PRO

> These instructions assume that you have no other SSH settings on the server.

> There are no return mechanisms from PRO to MIT. The solutions developed at PRO are not compatible with MIT. If solutions developed at MIT can be ported to PRO, the reverse porting does not guarantee workability.

Switch to the user from which Totum is running:

```
su totum
```

Go to the Totum installation folder:

```
cd ~/totum-mit
```

Write GitHub credentials to the local GIT in the Totum folder (replace `USERNAME_IN_GITHUB` and `EMAIL_IN_GITHUB`):

```
git config --local user.name USERNAME_IN_GITHUB
```
```
git config --local user.email EMAIL_IN_GITHUB
```

To check:

```
git config --list --local
```

Let's go to the folder with the keys:

```
cd ~/.ssh
```

Create an SSH key (replace `EMAIL_IN_GITHUB`):

```
ssh-keygen -t ed25519 -C "EMAIL_IN_GITHUB"
```

Enter the name of the key file - `totum_pro_key`.

Next will be a question about the key password — leave it blank and press `Enter`.

Press `Enter` again.

Create a config for the connection:

```
nano config
```

Insert and save (to save `Ctrl + X`, enter `Y` and `Enter`):

```
Host *
  AddKeysToAgent yes
  IdentityFile ~/.ssh/totum_pro_key
```

Display and copy the key (the whole line from the beginning of the algorithm name to the end of the email):

```
cat ~/.ssh/totum_pro_key.pub
```

> In some terminals, the email that is at the end of the key string may be copied with the tech tag.

> Therefore, the best option is to first paste the copied key into a plain text file and copy it again from there.

Go to the GitHub page [https://github.com/settings/keys](https://github.com/settings/keys) and add the key to the SSH keys section (New SSH Key). Enter the name and insert a key string of the form:

```
ssh-ed25519 [here_your_key] [your_email] 
```

Checking the connection to GH:

```
cd ~/totum-mit
```
```
ssh -T git@github.com
```
```
> The authenticity of host 'github.com (IP ADDRESS)' can't be established.
> RSA key fingerprint is SHA256:nThbg6kXUpJWGl7E1IGOCspRomTxdCARLviKw6E5SY8.
> Are you sure you want to continue connecting (yes/no)?
```
```
yes
```

You should see something like this:

```
Hi you_name! You've successfully authenticated, but GitHub does not provide shell access.
```

Switch to PRO.

> You can only switch from the latest version. Be sure to update your base before switching repository.

```
bin/totum git-update
```

Connecting the PRO repository:

```
git remote set-url origin git@github.com:totumonline/totum-pro.git
```

Download data:

```
git fetch origin pro
```

Switching to the new pro branch:

```
git checkout -b pro origin/pro -t
```

Let's update the scripts:

```
git pull
```

Go to Totum and check the version - PRO versions have `-NUM` at the end. For example `3.7.47.5-3`.

You are now connected to the PRO repository and the standard command `bin/totum git-update` will update to the PRO version.

## Install MeiliSearch

Execute from the root:

```
echo "deb [trusted=yes] https://apt.fury.io/meilisearch/ /" > /etc/apt/sources.list.d/fury.list
```
```
apt update && apt install meilisearch-http
```

Switching to the user Totum:

```
su totum
```

Go to the home folder:

```
cd ~
```

Create a key file and type an random key there using `a` - `z`, `A` - `Z`, `0` - `9` (to save `Ctrl + X`, type `Y` and `Enter`):

```
nano meili_masterkey
```

Start the search server:

```
meilisearch --no-analytics --db-path ./meilifiles --env production --master-key $(cat meili_masterkey) &
```

Record the launch of the search engine when the server reboots:

```
crontab -e
```

We add a line to the end:

```
@reboot cd ~ && exec meilisearch --no-analytics --db-path ./meilifiles --env production --master-key $(cat meili_masterkey) > /dev/null 2>&1 &
```

> The last row in the `crontab` must necessarily be empty!

To save `Ctrl + X`, enter `Y` and `Enter`.

Further settings are performed in the Totum scheme.
