. $HOME/WebAdmin/.bashrc
[ "$PS1" ] || return
alias backup='date >| $HOME/.rsync.log ; rsync -abq --log-file="$HOME/.rsync.log" --exclude-from=$HOME/.backup-exclude --backup-dir="@pre-"`date "+%Y%m%d%H%M"` --delete --delete-excluded --partial-dir=".rsync-partial" $HOME/ "imac:/Users/guy/Backups/bizwiz@melody/" &'
alias bzx='bzr explorer "`bzr root`" &>/dev/null &'
