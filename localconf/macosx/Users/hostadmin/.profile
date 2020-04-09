
export PATH=$HOME/scripts:/opt/local/libexec/gnubin:/opt/local/bin:/usr/local/bin:/usr/bin:/bin:/opt/local/sbin:/usr/sbin
export MANPATH=/opt/local/share/man:/usr/local/share/man:/usr/share/man

# Wine config - Wine is provided by MacPorts
export WINEPREFIX=$HOME/.wine
export DYLD_FALLBACK_LIBRARY_PATH=/usr/X11/lib

. ~/WebAdmin/.profile
. ~/.bashrc # this is OK because all operations in there check $PS1 before proceeding

