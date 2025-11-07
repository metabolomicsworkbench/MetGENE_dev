library(data.table)
library(curl)
library(stringi)

refmet_convert_fun   <-  function(DF) { 
# Mano: To run: tic();source("refmet_convert_faster.R");toc() # don't tic toc here if time_code = 1 below

time_code = 0;
if(time_code) { library(tictoc); }

#Text file with metabolite names (one per line). First line should be a heading e.g. "NAME", "METABOLITE_NAME", "ID", etc

####Example below
#NAME
#Ceramide d18:0/26:2
#citrate
#Trilauroyl-glycerol
#PE(aa-40:4)
#HMDB00201
#C00099
#Octenoyl-L-carnitine



# Note: DF[,1] can be any data frame column containing metabolite names
mets=stri_join_list(list(DF[,1]), sep="\n")
h <- new_handle()
handle_setform(h,  metabolite_name = mets)

# For comparison: TG: Example of rest call for one metabolite: https://www.metabolomicsworkbench.org/rest/refmet/kegg_id/C00422/name/
# ref query:
# select count(*) from (select distinct name,kegg_id,pubchem_cid,exactmass,formula,super_class,main_class,sub_class from refmet where kegg_id='C00641') tmp; # DF
# select distinct name,kegg_id,pubchem_cid,exactmass,formula,super_class,main_class,sub_class from refmet where kegg_id='C00641'; # DG

#run the RefMet request on the Metabolomics Workbench server
#req <- curl_fetch_memory("https://www.metabolomicsworkbench.org/databases/refmet/name_to_refmet_new_min.php", handle = h)
# Mano: 2023/07/06 (07/06/2023): Increased limit for kegg_id query to 100000

if(time_code==1) { tic("Time to get results from server through curl_fetch_memory: "); }
req <- curl_fetch_memory("https://www.metabolomicsworkbench.org/databases/refmet/name_to_refmet_new_min_metgene.php", handle = h)
if(time_code==1) { toc(); }

# Mano: Try to use read.table

#########################
if(time_code==1) { tic("Time to convert the results into a data.frame: "); }

processing_option = 2;
if(processing_option==1){
    # This block contains Eoin's original code for parsing
    #Parse the output
    x<-rawToChar(req$content)
    y<-strsplit(x,"\n")
    #refmet <- data.frame(matrix(NA, nrow=(length(y[[1]])+1)/2, ncol = 7))
    refmet <- data.frame(matrix(NA, ncol = 7))

    for (i in 1:length(y[[1]])){
    	if(nchar(y[[1]][i])>1){
    		z<-strsplit(y[[1]][i],"\t")
    		for (j in 1:length(z[[1]])){
    			refmet[i,j]<-z[[1]][j]
    		}
    	}
    }

    refmet<-refmet[rowSums(is.na(refmet)) != ncol(refmet), ]
    colnames(refmet)=refmet[1,]
    refmet<-refmet[-c(1),]
} else {
    refmet <- read.table(text = rawToChar(req$content), header = TRUE, na.strings = "-", stringsAsFactors = FALSE, quote = "", comment.char = "", sep="\t");
}

refmet[is.na(refmet)] <- ''

if(time_code==1) { toc(); }
#########################


return(refmet)
}
#The Standardized name column contains the RefMet names
#infile='met_list.txt'
#infile='met_list3.txt'
#Load the example names into a data frame
#metabID = c("C00422")
#metDF <-as.data.frame(metabID)
#metDF <-as.data.frame(fread(infile,header=TRUE,fill=TRUE))
#refmetOut = refmet_convert_fun(metDF)

#print(paste0("remet: number of rows: ", nrow(refmetOut), "; Printing head(refmet)"));
#print(head(refmetOut))
