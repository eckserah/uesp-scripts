#!/usr/bin/python26
#
# uespdeploy.py
#   by Dave Humphrey (dave@uesp.net), created on 5 Jan 2014
#
# A basic script for deploying software onto one or more servers on
# the UESP.net website.
#
# Run 'uespdeply.py -h' to see basic help on command line options.
# By default it tries to load and rin the 'uesp.deploy' file in the
# current directory. Command line options will override data from the
# deployment data file.
#

import os
import socket
import datetime
from subprocess import call
from optparse import OptionParser
import sys
import MySQLdb as mdb
import getpass

#
# Constants
#
SECRETS_FILE = "/home/uesp/secrets/uespdeploy.secrets"
DEFAULT_DEPLOY = "uesp.deploy"
DEFAULT_SOURCEPATH = "./"
DEFAULT_BACKUPPATH = "/tmp/"
COMMENT_CHAR = '#'

DB_SERVER = "content3.uesp.net"
DB_PORT = 3306
DB_DATABASE = "uesp_deploy"
DB_TABLE = "deploylog"

    # The following two will be set within the external secrets file loaded at run time
DB_USER = ""
DB_PASSWORD = ""

#
# Global variables
#
g_InputOptions = []
g_InputArgs = []
g_DeployParams = []
g_SourcePath = DEFAULT_SOURCEPATH
g_LastBackupPath = ""

g_DB = None


def LoadSecrets():
    fp = open(SECRETS_FILE)
    secrets = fp.read()
    fp.close()
    exec(secrets) in globals()


def CreateDeployTable():
    if (IsVerbose()): print "Creating table {0}.{1}".format(DB_DATABASE, DB_TABLE)
    
    HeaderStr = "CREATE TABLE {0}.{1} (".format(DB_DATABASE, DB_TABLE);
    TableDef = """
                id INT NOT NULL AUTO_INCREMENT PRIMARY KEY,
                username VARCHAR(64) NOT NULL,
                timestamp TIMESTAMP NOT NULL DEFAULT NOW(),
                appname VARCHAR(64) NOT NULL,
                options TEXT NOT NULL,
                source TEXT NOT NULL,
                destination TEXT NOT NULL,
                backuppath TEXT NOT NULL,
                deployfile TEXT NOT NULL,
                error TEXT NOT NULL
                """
    QueryStr = HeaderStr + TableDef + ");"
    if (IsVerbose()): print "\t{0}".format(QueryStr)
    g_DB.query(QueryStr)
    return True


def InitDatabase():
    global g_DB
    
    if (IsVerbose()): print "Trying to connect to MySQL database {0} on {1}:{2} as {3}".format(DB_DATABASE, DB_SERVER, DB_PORT, DB_USER)
    g_DB = mdb.connect(host=DB_SERVER, user=DB_USER, passwd=DB_PASSWORD, db=DB_DATABASE, port=DB_PORT)
    
    QueryStr = "SELECT * FROM information_schema.tables WHERE table_schema='{0}' AND table_name='{1}' LIMIT 1;".format(DB_DATABASE, DB_TABLE)
    g_DB.query(QueryStr)
    Results = g_DB.store_result()
    
    if (Results.num_rows() <= 0):
        CreateDeployTable()

    return True


def CloseDatabase():
    if (g_DB): g_DB.close()
    return True


def AddDeployLog(destination, error=""):
    OptionStr = g_DB.escape_string(', '.join('%s=%s' % (k,v) for k,v in vars(g_InputOptions).items()))
    
    with open (g_InputOptions.deployfile, "r") as myfile:
        DeployFile = g_DB.escape_string(myfile.read())
    
    HeaderStr = "INSERT INTO {0}.{1} (username, appname, options, source, destination, backuppath, error, deployfile) ".format(DB_DATABASE, DB_TABLE)
    ValueStr = "VALUES ('{0}', '{1}', \"{2}\", '{3}', '{4}', '{5}', '{6}', '{7}');".format(
                                                                                    g_DB.escape_string(getpass.getuser()),
                                                                                    g_DB.escape_string(GetDeployParamValue("name")),
                                                                                    OptionStr,
                                                                                    g_DB.escape_string(g_SourcePath),
                                                                                    g_DB.escape_string(destination),
                                                                                    g_DB.escape_string(g_LastBackupPath),
                                                                                    g_DB.escape_string(error),
                                                                                    DeployFile) 
    QueryStr = HeaderStr + ValueStr
    
    if (IsVerbose()): print "\tAdding deploy log database row:\n\t\t{0}".format(QueryStr)
    
    g_DB.query(QueryStr)
    
    return True


def ParseInputArgs():
    parser = OptionParser()
    parser.add_option("-v", "--verbose",    action="store_true",    dest="verbose",     help="increase output verbosity")
    parser.add_option("-s", "--source",     action="store",         dest="source",      help="path to the source being deployed (defaults to the current local directory)", type="string", default=DEFAULT_SOURCEPATH)
    parser.add_option("-d", "--destination",action="store",         dest="destination", help="destination path for the installation", type="string")
    parser.add_option("-f", "--file",       action="store",         dest="deployfile",  help="use the specified deployment file instead of uesp.deploy", type="string", default=DEFAULT_DEPLOY)
    parser.add_option("-H", "--hostname",   action="store",         dest="hostname",    help="manually specify the localhost's name", type="string")
    parser.add_option("-b", "--backup",     action="store_true",    dest="backup",      help="backup destination files to the tmp directory")
    parser.add_option("-B", "--backuppath", action="store",         dest="backuppath",  help="destination path for any backups") 
    return parser.parse_args()


def GetDeployParamValue(Param):
    Result = GetDeployParam(Param)
    if (len(Result) == 0): return ""
    if (len(Result[0]) <= 1): return ""
   
    return Result[0][1]


def GetDeployParam(Param):
    global g_DeployParams
    tmpParam = Param.lower()
    
    Result = [item for item in g_DeployParams if item[0] == tmpParam]
    return Result


def GetHostName():
    global g_InputOptions
    if (g_InputOptions.hostname): return g_InputOptions.hostname;
    return socket.gethostname()


def IsVerbose():
    global g_InputOptions
    
    if (GetDeployParamValue("verbose").lower() == "true"): return True
    if (g_InputOptions.verbose): return True

    return False


def IsBackup():
    global g_InputOptions
    
    if (GetDeployParamValue("backup").lower() == "true"): return True
    if (g_InputOptions.backup): return True

    return False


def GetBackupPath():
    global g_InputOptions    
    BackupPath = g_InputOptions.backuppath;
    
    if (BackupPath):
        if (not BackupPath.endswith('/')): BackupPath += '/'
        return BackupPath
    
    BackupPath = GetDeployParamValue("backuppath")
    
    if (BackupPath):
        if (not BackupPath.endswith('/')): BackupPath += '/'
        return BackupPath
    
    return DEFAULT_BACKUPPATH


def GetSourcePath():
    SourcePath = g_InputOptions.source
    if (not SourcePath.endswith('/')): SourcePath += '/'
    return SourcePath


def ExtractServerName(path):
    TmpSplit = path.split(':', 1)
    if (len(TmpSplit) == 1): return "localhost";
    return TmpSplit[0]


def LoadDeployFile(filename):
    global g_DeployParams
    print "Loading deploy file '{0}'...".format(filename)

    f = open(filename)
    lines = f.readlines()
    f.close()

    for line in lines:
        newline = line.split(COMMENT_CHAR)[0].strip()
        if (not newline): continue
        
        variable, value = newline.split('=', 1)
        variable = variable.strip().lower()
        value = value.strip()
        g_DeployParams.append((variable, value))
        
        if (IsVerbose()): print "\t{0} = {1}".format(variable, value)        

    if (IsVerbose()): print "\tFound and parsed {0} parameters".format(len(g_DeployParams))


def CreateRsyncCommand(source, dest, optargs=[]):
    Cmd = ["rsync", "-azIm", "--delete-excluded"]
    Cmd += optargs

    if (IsVerbose()): Cmd.append("-v")

    IgnoreFiles = GetDeployParam("ignore")

    for file in IgnoreFiles:
        if (len(file) <= 1): continue
        Exclude = "--exclude={0}".format(file[1])
        Cmd.append(Exclude)

    Cmd.append(source)
    Cmd.append(dest)

    if (IsVerbose()): print "\trsync cmd: {0}".format(Cmd)
    return Cmd


def CreateBackup(destination):
    global g_LastBackupPath
    
    print "\tCreating backup of existing source in '{0}'...".format(destination)
    BackupPath = GetBackupPath()
    BackupPath += "{0}_{1}_{2}/".format(GetDeployParamValue("name"), ExtractServerName(destination), datetime.datetime.now().strftime("%Y%m%d%H%M%S"))
    if (IsVerbose()): print "\tBacking up files to '{0}'...".format(BackupPath)
    g_LastBackupPath = BackupPath

    if not os.path.exists(BackupPath):
        os.makedirs(BackupPath)

    RsyncCmd = CreateRsyncCommand(destination, BackupPath)
    Result = call(RsyncCmd)
    if (Result != 0): return False
        
    return True


def DeployFiles(destination):
    RsyncCmd = CreateRsyncCommand(g_SourcePath, destination)
    Result = call(RsyncCmd)
    if (Result != 0): return False
    return True


def DeployDeleteFiles(destination):
    DeletedFiles = GetDeployParam("deletefromdest")

    for file in DeletedFiles:
        if (len(file) <= 1): continue

        filename = destination + file[1]

            # TODO: Fails for remote files
        if (os.path.exists(filename)):
            if (IsVerbose()):
                print "\tDeleting file '{0}' from destination.".format(filename)
            os.remove(filename)

    return True


def DoOneDeploy(destination):
    print "Deploying to '{0}'...".format(destination)

    if (IsBackup()):
        if (not CreateBackup(destination)):
            print "\tBackup failed...aborting deployment!"
            return False

    if (not DeployFiles(destination)):
        print "\tError copying files with rsync...aborting deployment!"
        return False

    if (not DeployDeleteFiles(destination)):
        print "\tError deleting files from deployment path!"
        return False

    AddDeployLog(destination)
    print "\tSuccessfully completed deployment!"

    return True


def DoDeploy():
    
    if (g_InputOptions.destination):
        return DoOneDeploy(g_InputOptions.destination)

    AllDests = GetDeployParam("dest")

    if (len(AllDests) == 0):
        print "Error: No deployment destinations specified!"
        return False

    for destination in AllDests:
        if (len(destination) > 1):
            DoOneDeploy(destination[1])
   
    return True


#
# Begin Main Program
#
(g_InputOptions, g_InputArgs) = ParseInputArgs()
LoadDeployFile(g_InputOptions.deployfile)

if (IsVerbose()):
    print "Local hostname is '{0}'".format(GetHostName())

LoadSecrets()
InitDatabase()


g_SourcePath = GetSourcePath()
print "Installing from local path '{0}'".format(g_SourcePath)

DoDeploy()

CloseDatabase()