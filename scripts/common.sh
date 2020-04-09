#!/bin/bash
# hostadmin/scripts/common.sh
#
# Establishes a common runtime environment for shell scripting within the hosted account context
#
# Created by Biz Wiz on 2/1/15.
# All original code.
# Copyright © 2015 Lifetoward LLC
# All rights reserved.

# Follow the template below to fully define a well-enabled function.
# Caveat: Your usage text exists in shell double-quote context. Don't use double-quotes (") within the usage text! It's very complicated to escape them.

cat > /dev/null <<FunctionDefTemplate

FNAME=yourFunctionNameHere
eval "export usage_$FNAME=\"usage:  $FNAME  yourCommandLineOptionDescriptions

yourAdditionalUsageInformation
\""
yourFunctionNameHere () {
    fixReturn
    [ yourRequiredCondition ] || echo "yourErrorMessageText" | throwAlert 1
}
export -f $FNAME && echo added function $FNAME >&2

FunctionDefTemplate


# BEGIN SECTION FOR FUNCTION SUPPORT # # # # # # # #

# functionUsage allows for convenient usage message reporting to stderr from a function context
# usage: { fucntionUsage $FUNCNAME $MESSAGE ; return $RETURNVALUE ; }
# if $FUNCNAME is null or nonsense, you won't get a usage message
functionUsage () {
    { [ "$2" ] && echo "$1: $2" ; [ "$PS1" -a "$1" ] && { echo ; eval echo \"\$usage_$1\" ; }  } >&2
}
export -f functionUsage

# shell function aliases
# these facilitate shorthand implementations of common command sequences in standard functions
# remember, these should be considered transient, as they are defined-in to the functions defined in this shell, but do not export to subshells
shopt -s expand_aliases
# fixReturn saves the environment of the calling shell so when the function returns all is restored
alias fixReturn='pushd . &>/dev/null ; trap "[ \"\$FUNCNAME\" = \"$FUNCNAME\" ] && { popd &>/dev/null ; trap RETURN ; PATH=\"$PATH\"; }; set +x" RETURN'
# funcUsage provides a way for any function to easily code an "exit with message and usage statement" procedure for error conditions
alias usage='functionUsage $FUNCNAME'

dow=`date +%a` ; export DayOfWeek="${dow,,}."

# BEGIN SECTION FOR COMMON FUNCTIONS # # # # # # # #


FNAME=addpath
eval "export usage_$FNAME=\"usage: $FNAME  [ {directory} [ ... ] ]

For each argument given, addpath ensures it is a traversable directory and canonicalizes it (normalizing symlinks).
Then the current path is checked to see if the directory is already there.
If the canonical directory is not already in your path, it is added to the front of the path.
Otherwise the argument is skipped and any other paths provided are processed in the same way.

$FNAME always displays the current path to stdout (&1) as it finishes.
Without an argument, $FNAME displays this message to stdout as well.
Handling of individual arguments (as added or not) is reported to stderr (&2).
\""
function addpath {
    [ "$1" ] || usage
    while [ "$1" ]
    do
        if cd -P "$1" &>/dev/null
        then
            [ "$PATH" = "${PATH##*$PWD:}" ] && # A match implies the new directory is NOT present (except maybe at the end)
                { export PATH="$PWD:$PATH" ; echo "Directory \"$PWD\" prepended on \$PATH" ; } ||
                echo "(Directory \"$PWD\" is already present. Skipped.)"
            cd - &>/dev/null
        else
            echo "$FUNCNAME: Not a directory: \"$1\""
        fi
        shift
    done >&2
    echo "Path: $PATH"
}
export -f $FNAME && echo added function $FNAME >&2
alias prepath=addpath # for legacy reasons


FNAME=findall
eval "export usage_$FNAME=\"usage: $FNAME [ -w{more_grep_options} | -g{grep_options} ] { search_regex } [ path_top ] [ find_options ... ]
        search_regex is an extended regex of the text you're searching for in the files in the tree.
        path_top defaults to '.', i.e. the current directory; if it's not a directory, we assume it's not a path_top.
        grep_options defaults to 'li' to provide a list of files containing the text sought using case insensitive matching;
            your specification overrides this entirely to fully specify egrep's options
                NOTE THE LACK OF SPACE BETWEEN THE -g AND THE GREP_OPTIONS! (That's intended. See examples.)
        The -w ('where') option is the same as -gHni, ie. show matching lines along with their locations.

notes:
        Your find_options will appear first in the order of arguments to the find command.
        We always exclude .bzr and .idea directories and only process file names which don't end in ~ .
        The last option to the find command will be the -exec egrep operation.

examples:
        $FNAME actionresult ; # simple case insensitive search in current directory's hierarchy producing list of filenames
        $FNAME -w capcelaction lib -maxdepth 2 -name '*.php' ; # matches with locations from PHP files, shallow from lib/
        $FNAME -g CapcelContext -name '*.php'; # case sensitive dump of only matching lines from PHP files
\""
findall () {
    [ -z "$1" ] && { usage "Args are required."; return 1 ; }
    local GREPOPTS=li NAMESPEC= FINDARGS="-type f ! -path '*/.bzr/*' ! -path '*/.idea/*' ! -name '*~'"
    while [ "$1" ]
    do
        case "$1" in
            -w*)  GREPOPTS="nHi${1#-w}" ; shift ;;
            -g*)  GREPOPTS="${1#-g}" ; shift ;;
            -?|-h) findall ; return ;;
            -*)  echo "Option '$1' ignored." ; shift ;;
            *)  break ;; # a non-option breaks the loop
        esac
    done
    [ -z "$1" ] && { findall ; return ; }
    local EXPR="$1" TOP=
    shift
    [ "$1" -a -d "$1" ] && { TOP="$1" ; shift ; }
    local CMD="find '${TOP:-.}' $@ $FINDARGS -exec egrep -${GREPOPTS:-e} '$EXPR' {} \;"
    echo $CMD >&2
    eval $CMD
}
export -f $FNAME && echo added function $FNAME >&2

FNAME=validateEmailAddress
validateEmailAddress () {
    if echo "${1,,}" | egrep '^[a-z0-9._-]+@[a-z0-9]+(\.[a-z]*)+$' &>/dev/null
    then EMAIL="${1%@*}" DOMAIN="${1#*@}"
    else echo "${2:-$FUNCNAME}:" "\"$1\" is not of a valid email address format." >&2 ; return 1
    fi
}
export -f $FNAME && echo added function $FNAME >&2


FNAME=sendEmail
eval "export usage_$FNAME=\"usage:  $FNAME -to { to_address[,...] } [ -subject { subject } ] [ -from { from_address } ] [ -cc { copy_address[,...] } ] [ -bcc { blind_copy[,...] } ]
    You can provide the options in any order, however valid options must be followed by exactly one value.
    Be sure to quote properly!

The email body, which begin with any additional headers and a blank line, must be provided on STDIN.

To, Cc, and Bcc addresses may be provided as comma-separated lists.
    Providing multiple instances of a particular option appends to these lists.

Any address can be provided in rfc822 format as long as it's properly quoted, ie: 'Full Name <account@example.com>'.
\""
sendEmail () {
    local to="To: " from="From: WebAdmin@SyntheticWebApps.com" subject="Subject: (no subject)" cc="Cc: " bcc="Bcc: " rcpt=
    while [ "${1#-}" != "$1" ]
    do
        optval="${1#-}" ; shift
        case "$optval" in
          to|cc|bcc)
            eval $optval+="\"$1,\"" ; rcpt+="$1," ; shift ;;
          from)
            eval $optval=\"From: "$1"\" ; shift ;;
          subject)
            eval $optval=\"Subject: "$1"\" ; shift ;;
        esac
    done
    [ "$rcpt" ] ||
        { echo "At least one addressee is required." ; eval echo \"\$${FUNCNAME}_\" ; return 1 ; }
    {   echo "$from"
        echo "${to%,}"
        [ "${cc#Cc: }" ] && echo "${cc%,}"
        echo "X-Mailer: sendEmail function @ $WEBHOST_DOMAIN"
        echo -e "$subject\n"
        cat
    } | sendmail "${rcpt%,}"
}
export -f $FNAME && echo added function $FNAME >&2


FNAME=setServiceSite
eval "export usage_$FNAME=\"usage:  $FNAME  {service}

Searches the HostAcct's sites database file for the FIRST site with the specified service configured.
Sets (exports) the site environment variables SITE, DOMAIN, PORTBASE, and SERVICES for the site found.
Returns false otherwise.

This is not the function to use for services which can be configured for multiple sites per account.
Use getServiceSites for that purpose.
\""
setServiceSite () {
    local sitesDb="$ACCOUNT_HOME"/etc/sites site domain portbase services
    [ "$ACCOUNT_HOME" -a -r "$sitesDb" ] ||
        { usage "ACCOUNT_HOME not set" ; return -1 ; }
    [ $1 ] || { usage ; return 1 ; }
    while read site domain portbase services
    do [ -z "${site%%#}" ] && break # implements valid records logic for singleton services
        for s in $services ; do [ $s = $1 ] && break 2 ; done
    done < "$sitesDb"
    [ "$s" = "$1" ] || { echo "Service '$1' not configured for any site for hostacct '$ACCOUNT'" >&2 ; return 2 ; }
    export SITE=$site DOMAIN=$domain SERVICES="$services"
    echo "Configured environment for site $SITE." >&2
}
export -f $FNAME ; echo "added function $FNAME" >&2

