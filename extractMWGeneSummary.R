#!/usr/bin/env Rscript
# THis script extracts the summary information pertaining to the gene input
# Call syntax: Rscript extractMWGeneSummary.R <species> <geneIDArr> <geneSymArr>  <filename> <viewType> <anatomy> <disease>
# Input: species e.g. hsa, mmu
#        geneIdArr : e.g. 3098, 6120 (ENTREZID of genes)
#        geneStrArr : e.g. HK1, RPE
#        filename: e.g. plot.png for the summary plot
#        viewType : e.g. json, txt, png, bar, table, all (default is pie chart)
# Output: A table in json or txt format comprising of summary (Pathways, Reactions, Metabolites, Studies) information
#       : A html table (if view type is table)
#       : A bar plot (viewType is bar)
#       : A pie chart, and table for all
# Example: Rscript extractMWGeneSummary.R hsa 3098__6120 HK1__RPE plot.png all blood diabetes
# susrinivasan@ucsd.edu; mano@sdsc.edu

################################################
# Restrictions due to the use of KEGG APIs (https://www.kegg.jp/kegg/legal.html, see also https://www.pathway.jp/en/academic.html)
# * Using this code to provide user's own web service
# The code we provide is free for non-commercial use (see LICENSE). While it is our understanding that no KEGG license is required to run the web app on user's local computer for personal use (e.g., access as localhost:install_location_withrespectto_DocumentRoot/MetGENE, or, restrict its access to the IP addresses belonging to their own research group), the users must understand the KEGG license terms (https://www.kegg.jp/kegg/legal.html, see also https://www.pathway.jp/en/academic.html) and decide for themselves. For example, if the user wishes to provide this tool (or their own tool based on a subset of MetGENE scripts with KEGG APIs) as a service (see LICENSE), they must obtain their own KEGG license with suitable rights.
# * Faster version of MetGENE
# If and only if the user has purchased license for KEGG FTP Data, they can activate a 'preCompute' mode to run faster version of MetGENE. To achieve this, please set preCompute = 1 in the file setPrecompute.R. Otherwise, please ensure that preCompute is set to 0 in the file setPrecompute.R. Further, to use the faster version, the user needs to run the R scripts in the 'data' folder first. Please see the respective R files in the 'data' folder for instructions to run them.
# Please see the files README.md and LICENSE for more details.
################################################

## Linux
library(KEGGREST)
library(rlang)
library(stringr)
library(data.table)
library(xtable)
library(jsonlite)
library(tictoc)

library(utils)
library(textutils)
library(tuple)
library(tidyr)
library(ggplot2)
library(reshape2)
library(ggrepel)
library(tidyverse)

source("setPrecompute.R")

getRxnIDsPthwyIDsCpdIDsFromKEGG <- function(queryStr) {
    # Fetch enzyme information from KEGG
    #print(paste0("QueryStr = ", queryStr))
    kegg_data <- keggGet(queryStr)
    if (length(kegg_data) == 0 || is.null(kegg_data[[1]]$ORTHOLOGY)) {
        stop("Invalid KEGG entry or no ORTHOLOGY information found.")
    }

    enzyme <- kegg_data[[1]]$ORTHOLOGY[[1]]

    # Extract EC number
    ec_number <- regmatches(enzyme, regexpr("EC:\\d+\\.\\d+\\.\\d+\\.\\d+", enzyme))
    if (length(ec_number) == 0) {
        stop("No EC number found in ORTHOLOGY field.")
    }

    ec_number <- tolower(ec_number) # Convert to lowercase, e.g., ec:1.1.1.1

    # Get reaction IDs
    rxns <- keggLink("reaction", ec_number)
    rxn_vec <- unname(as.vector(rxns))

    # Create a dataframe for reactions
    reaction_labels <- paste("reaction", seq_along(rxn_vec))
    rxn_df <- data.frame(
        Type = reaction_labels,
        ID = rxn_vec,
        stringsAsFactors = FALSE
    )

    # Get compound IDs
    cpds <- keggLink("compound", ec_number)
    cpd_vec <- unname(as.vector(cpds))

    # Create a dataframe for compounds
    compound_labels <- paste("compound", seq_along(cpd_vec))
    cpd_df <- data.frame(
        Type = compound_labels,
        ID = cpd_vec,
        stringsAsFactors = FALSE
    )

    # Get pathway IDs
    pthwys <- keggLink("pathway", queryStr)
    pthwy_vec <- unname(as.vector(pthwys))
    # Create a dataframe for pathways
    pathway_labels <- paste("pathway", seq_along(pthwy_vec))
    pthwy_df <- data.frame(
        Type = pathway_labels,
        ID = pthwy_vec,
        stringsAsFactors = FALSE
    )

    # Combine the dataframes
    combined_df <- rbind(rxn_df, cpd_df, pthwy_df)

    return(combined_df)
}

list_of_list_to_df <- function(jslist) {
    cols_needed <- c("refmet_name", "kegg_id", "study", "study_title")
    if (length(jslist) == 0) {
        jsdf <- NULL
    } else {
        if (class(jslist[[1]]) == "list") {
            # loop over:
            n <- length(jslist)
            for (i in 1:n) {
                if (i == 1) {
                    jsdf <- as.data.frame(t(as.data.frame(unlist(jslist[[i]]))))
                    jsdf <- jsdf[, cols_needed]
                } else {
                    jsdf_tmp <- as.data.frame(t(as.data.frame(unlist(jslist[[i]]))))
                    jsdf_tmp <- jsdf_tmp[, cols_needed]
                    jsdf <- rbind(jsdf, jsdf_tmp)
                }
            }
            rownames(jsdf) <- as.character(c(1:n))
        } else { # only one item
            jsdf <- as.data.frame(t(as.data.frame(unlist(jslist))))
            jsdf <- jsdf[, cols_needed]
            rownames(jsdf) <- "1"
        }
    }
    return(jsdf)
}

printvar <- function(x, xpr = NA) {
    write(paste0(deparse(substitute(x)), " = "), "")
    if (!is.na(xpr)) {
        write(xpr, "")
    }
    print(x)
    if (!is.na(xpr)) {
        write(xpr, "")
    }
}

cleanFun <- function(htmlString) {
    return(gsub("<.*?>", "", htmlString))
}
########################################################

plotSummary <- function(countMatrix, genesCnt, symbolStrArray, organism_name, pathwaysLinkStr, rxnsLinkStr, metsLinkStr, studiesLinkStr, plotFile, viewType) {
    ##  create specified view type as an image
    currDir <- paste0("/", basename(getwd()))
    if (nrow(countMatrix) == 0) {
        return(cat(paste0("<p> No summaries found in Metabolomics Workbench for the specified genes.</p>")))
    }
    categories <- c(rep("Pathways", genesCnt), rep("Reactions", genesCnt), rep("Metabolites", genesCnt), rep("Studies", genesCnt))
    Genes <- rep(as.vector(symbolStrArray), 4)
    values <- as.vector(countMatrix)
    data <- data.frame(categories, Genes, values)
    titleStr <- organism_name

    if (genesCnt > 0) {
        rplot <- ggplot(data, aes(x = "", y = values, fill = Genes)) +
            geom_bar(stat = "identity", position = position_fill()) +
            geom_text(aes(label = values), position = position_fill(vjust = 0.5), size = 6) +
            theme_void() +
            theme(plot.title = element_text(color = "blue", size = 20, face = "bold")) +
            theme(plot.title = element_text(hjust = 0.5)) +
            theme(plot.title = element_text(vjust = 0.75)) +
            theme(strip.text.x = element_text(size = 20)) +
            labs(title = titleStr) +
            facet_wrap(~categories) +
            theme(
                axis.title.x = element_blank(),
                axis.title.y = element_blank()
            ) +
            theme(legend.position = "bottom") +
            theme(legend.text = element_text(size = 20)) +
            theme(legend.title = element_text(size = 20)) +
            guides(fill = guide_legend(ncol = 4, byrow = TRUE))

        tabDF <- as.data.frame(countMatrix)
        #print(tabDF)
        # Modified column name assignment:
         

        #colnames(tabDF) <- c("Pathways", "Reactions", "Metabolites", "Studies")
        colnames(tabDF) = c(pathwaysLinkStr, rxnsLinkStr, metsLinkStr, studiesLinkStr);
        #print(tabDF)
        rownames(tabDF) <- symbolStrArray
        tabDF[] <- lapply(tabDF, function(x) {
            as.integer(x)
        })
        nprint <- nrow(tabDF)
        vtFlag <- tolower(viewType)

        if (vtFlag == "all") {
            rplot <- rplot +
                coord_polar(theta = "y")

            ggplot2::ggsave(plotFile, plot = rplot, device = "png")
            cat(paste0("<table><tr><td><a href=\"", plotFile, "\" download>", "<img src=", currDir, "/", plotFile, " height=300 width=320 alt = R Graph></a></td><td>"))
            return(print(xtable(tabDF[1:nprint, ]), type = "html", include.rownames = TRUE, sanitize.text.function = function(x) {
                x
            }, html.table.attributes = "class='styled-table'  id='Table1' "))
        } else if (vtFlag == "bar") {
            ggplot2::ggsave(plotFile, plot = rplot, device = "png")
            cat(paste0("<a href=\"", plotFile, "\" download>", "<img src=", currDir, "/", plotFile, " height=300 width=320 alt = R Graph></a>"))
            return(print(xtable(tabDF[1:nprint, ]), type = "html", include.rownames = TRUE, sanitize.text.function = function(x) {
                x
            }, html.table.attributes = "class='styled-table' style='display:none' id='Table1'"))
        } else if (vtFlag == "json") {
            colnames(tabDF) <- cleanFun(colnames(tabDF))
            tabDF$Genes <- rownames(tabDF)
            tabJson <- toJSON(x = tabDF, pretty = T)
            return(cat(tabJson))
        } else if (vtFlag == "txt") {
            colnames(tabDF) <- cleanFun(colnames(tabDF))
            tabDF$Genes <- rownames(tabDF)
            return(cat(format_delim(tabDF, ",")))
        } else if (vtFlag == "png") {
            rplot <- rplot +
                coord_polar(theta = "y")
            ggplot2::ggsave(plotFile, plot = rplot, device = "png")
            return(cat(paste0("Image generated")))
        } else if (vtFlag == "table") {
            return(print(xtable(tabDF[1:nprint, ]), type = "html", include.rownames = TRUE, sanitize.text.function = function(x) {
                x
            }, html.table.attributes = "class='styled-table' id='Table1'"))
        } else {
            rplot <- rplot +
                coord_polar(theta = "y")

            ggplot2::ggsave(plotFile, plot = rplot, device = "png")
            cat(paste0("<table><tr><td><a href=\"", plotFile, "\" download>", "<img src=", currDir, "/", plotFile, " height=200 width=220 alt = R Graph></a></td><td>"))

            return(print(xtable(tabDF[1:nprint, ]), type = "html", include.rownames = TRUE, sanitize.text.function = function(x) {
                x
            }, html.table.attributes = "class='styled-table' id='Table1'"))
        }
    } else {
        return(print(paste0("<p><i>No genes specified</i></p>")))
    }
}



getGeneSummaryInfoTableOld <- function(orgStr, geneIDArray, geneSymArray, anatomy = "NA", disease = "NA", plotFile, viewType) {
    currDir <- paste0("/", basename(getwd()))
    ## Do not unique here since NA will be uniqued
    symbolStrArray <- as.vector(strsplit(geneSymArray, split = "__", fixed = TRUE)[[1]])
    #    print(symbolStrArray)
    geneArray <- as.vector(strsplit(geneIDArray, split = "__", fixed = TRUE)[[1]])
    countMatrix <- matrix(ncol = 4, nrow = length(geneArray))
    #   obtain only metabolic genes
    metGeneSYMBOLFileName <- paste0("./data/", orgStr, "_metSYMBOLs.txt")
    metGeneVec <- readLines(metGeneSYMBOLFileName)
    symbolStrArray <- symbolStrArray[symbolStrArray %in% metGeneVec]

    if (orgStr %in% c("Human", "human", "hsa", "Homo sapiens")) {
        organism_name <- "Human"
    } else if (orgStr %in% c("Mouse", "mouse", "mmu", "Mus musculus")) {
        organism_name <- "Mouse"
    } else if (orgStr %in% c("Rat", "rat", "rno", "Rattus norvegicus")) {
        organism_name <- "Rat"
    }

    genesCnt <- length(symbolStrArray)


    rdsfilename <- paste0("./data/", orgStr, "_summaryTable.RDS")
    #        print(rdsfilename)
    sumTable <- readRDS(rdsfilename)
    #        print(head(sumTable))
    #        print(symbolStrArray)
    geneSumTable <- sumTable[sumTable$Genes %in% symbolStrArray, ]
    #        print(geneSumTable)
    # Convert the columns Pathways, Reactions, Metabolites, and Studies to numeric
    geneSumTable$Pathways <- as.numeric(geneSumTable$Pathways)
    geneSumTable$Reactions <- as.numeric(geneSumTable$Reactions)
    geneSumTable$Metabolites <- as.numeric(geneSumTable$Metabolites)
    geneSumTable$Studies <- as.numeric(geneSumTable$Studies)
    # Convert the subsetted dataframe to a matrix
    countMatrix <- as.matrix(geneSumTable[, c("Pathways", "Reactions", "Metabolites", "Studies")])

    # Transpose the matrix to match the desired format
    countMatrix <- unname(countMatrix)
    #print(countMatrix)


    pathwaysLinkStr <- paste0("<a href=\"", currDir, "/pathways.php?species=", orgStr, "&GeneIDType=ENTREZID", "&anatomy=", anatomy, "&disease=", disease, "&phenotype=", "NA", "&GeneInfoStr=", geneIDArray, "\" target=\"_blank\">Pathways</a>")
    rxnsLinkStr <- paste0("<a href=\"", currDir, "/reactions.php?species=", orgStr, "&GeneIDType=ENTREZID", "&anatomy=", anatomy, "&disease=", disease, "&phenotype=", "NA", "&GeneInfoStr=", geneIDArray, "\" target=\"blank\">Reactions</a>")
    metsLinkStr <- paste0("<a href=\"", currDir, "/metabolites.php?species=", orgStr, "&GeneIDType=ENTREZID", "&anatomy=", anatomy, "&disease=", disease, "&phenotype=", "NA", "&GeneInfoStr=", geneIDArray, "\" target=\"blank\">Metabolites</a>")
    studiesLinkStr <- paste0("<a href=\"", currDir, "/studies.php?species=", orgStr, "&GeneIDType=ENTREZID", "&anatomy=", anatomy, "&disease=", disease, "&phenotype=", "NA", "&GeneInfoStr=", geneIDArray, "\" target=\"blank\">Studies</a>")




    plotSummary(countMatrix, genesCnt, symbolStrArray, organism_name, pathwaysLinkStr, rxnsLinkStr, metsLinkStr, studiesLinkStr, plotFile, viewType)
}

getGeneSummaryInfoTableWithKeggQuery <- function(orgStr, geneIDArray, geneSymArray, anatomy = "NA", disease = "NA", plotFile, viewType) {
    ## Do not unique here since NA will be uniqued
    #print(geneIDArray)
    #print(geneSymArray)
    symbolStrArray <- as.vector(strsplit(geneSymArray, split = "__", fixed = TRUE)[[1]])
    geneArray <- as.vector(strsplit(geneIDArray, split = "__", fixed = TRUE)[[1]])
    countMatrix <- matrix(ncol = 4, nrow = length(geneArray))

    ## Obtain base directory
    currDir <- paste0("/", basename(getwd()))

    pathwayCnt <- 0
    rxnsCnt <- 0
    metabCnt <- 0
    studyCnt <- 0
    genesCnt <- length(geneArray)
    #print(paste0("Genes count = ", genesCnt))
    for (g in 1:genesCnt) {
        genePthwyCnt <- 0
        geneRxnsCnt <- 0
        geneMetabCnt <- 0
        geneIdStr <- geneArray[g]

        metabRxnList <- list()
        reactionsList <- list()
        # colnames(metdf) = c("KeGG Compound Id", "REFMET Name", "Reactions",  "Study Statistics")
        if (orgStr %in% c("Human", "human", "hsa", "Homo sapiens")) {
            organism_name <- "Human"
        } else if (orgStr %in% c("Mouse", "mouse", "mmu", "Mus musculus")) {
            organism_name <- "Mouse"
        } else if (orgStr %in% c("Rat", "rat", "rno", "Rattus norvegicus")) {
            organism_name <- "Rat"
        }
        queryStr <- paste0(orgStr, ":", geneIdStr)
        # Get all reactions for this gene
        df <- getRxnIDsPthwyIDsCpdIDsFromKEGG(queryStr)
        if (length(df) == 0) {
            return(cat(paste0("<p>No entries found in MetGENE for organism ", organism_name, " gene ", geneIdStr, ".</p>")))
        }
        # All metabolites pertainin go the gene are prefixed as cpd:
        cpds <- df[str_detect(df[, 2], "cpd:"), 2]
        # All reactions pertaining to the gene are prefixed as rn:
        rxns <- df[str_detect(df[, 2], "rn:"), 2]
        pthwys <- df[str_detect(df[, 2], "path:"), 2]
        pathwayList <- gsub("path:", "", pthwys)
        metabList <- gsub("cpd:", "", cpds)
        reactionsList <- gsub("rn:", "", rxns)



        #         metabCnt <- metabCnt+length(metabList);
        rxnsCnt <- rxnsCnt + length(reactionsList)
        pathwayCnt <- pathwayCnt + length(pathwayList)


        geneRxnsCnt <- length(reactionsList)
        genePthwyCnt <- length(pathwayList)


        geneStudyCnt <- 0
        allStudies <- c() # to collect all studies across metabolites

        if (length(metabList) > 0) {
            for (m in 1:length(metabList)) {
                metabStr <- metabList[[m]]

                if (anatomy == "NA") {
                    anatomy <- ""
                }
                if (disease == "NA") {
                    disease <- ""
                }
                anatomyQryStr <- anatomy
                #print(anatomyQryStr)
                diseaseQryStr <- disease
                # https://metabolomicsworkbench.org/rest/metstat/;;;human;Fibroblast%20cells;;C00031 works 
                # but https://metabolomicsworkbench.org/rest/metstat/;;;human;Fibroblast+cells;;C00031 does not
                # PHP encodes space to + so we have to replace it by %20
                pat_str = "\\+"; rep_str = "%20";
                #if (!is_empty(anatomyId) && length(anatomyId) > 0 && str_detect(anatomyId, "\\+")) {
                #    anatomyQryStr <- str_replace_all(anatomyId, "\\+", "%20")
                if (!is_empty(anatomy) && length(anatomy) > 0 && str_detect(anatomy, pat_str)) {
                    anatomyQryStr <- str_replace_all(anatomy, pat_str, rep_str)
                }

                if (!is_empty(disease) && length(disease) > 0 && str_detect(disease, pat_str)) {
                    diseaseQryStr <- str_replace_all(disease, pat_str, rep_str)
                }

                path <- paste0("https://www.metabolomicsworkbench.org/rest/metstat/;;;", organism_name, ";", anatomyQryStr, ";", diseaseQryStr, ";", metabStr)
                #print(paste0("Path = ", path))

                jslist <- read_json(path, simplifyVector = TRUE)
                mydf_studies <- list_of_list_to_df(jslist)
                #print(head(mydf_studies))
                studiesVec <- mydf_studies$study
                refMetVec <- mydf_studies$refmet_name

                metabCnt <- metabCnt + length(unique(refMetVec))
                geneMetabCnt <- geneMetabCnt + length(unique(refMetVec))
                allStudies <- c(allStudies, studiesVec) # collect studies
            }
            #print(paste0("allStudies = ", allStudies))
            if (geneMetabCnt == 0) {
                geneMetabCnt <- length(metabList)
            }
            geneStudyCnt <- length(unique(allStudies)) # count unique studies across all metabolites
        }
        #print(paste0("geneIdStr = ", geneIdStr, " genePthwyCnt = ", genePthwyCnt, " geneRxnsCnt = ", geneRxnsCnt, " geneMetabCnt = ", geneMetabCnt, " geneStudyCnt = ", geneStudyCnt))
        countMatrix[g, ] <- c(genePthwyCnt, geneRxnsCnt, geneMetabCnt, geneStudyCnt)
    }
    #    print(countMatrix);
    pathwaysLinkStr <- paste0("<a href=\"", currDir, "/pathways.php?species=", orgStr, "&GeneIDType=ENTREZID", "&anatomy=", "NA", "&disease=", "NA", "&phenotype=", "NA", "&GeneInfoStr=", geneIDArray, "\" target=\"_blank\">Pathways</a>")
    #    print(pathwaysLinkStr);
    rxnsLinkStr <- paste0("<a href=\"", currDir, "/reactions.php?species=", orgStr, "&GeneIDType=ENTREZID", "&anatomy=", "NA", "&disease=", "NA", "&phenotype=", "NA", "&GeneInfoStr=", geneIDArray, "\" target=\"blank\">Reactions</a>")
    metsLinkStr <- paste0("<a href=\"", currDir, "/metabolites.php?species=", orgStr, "&GeneIDType=ENTREZID", "&anatomy=", "NA", "&disease=", "NA", "&phenotype=", "NA", "&GeneInfoStr=", geneIDArray, "\" target=\"blank\">Metabolites</a>")
    studiesLinkStr <- paste0("<a href=\"", currDir, "/studies.php?species=", orgStr, "&GeneIDType=ENTREZID", "&anatomy=", "NA", "&disease=", "NA", "&phenotype=", "NA", "&GeneInfoStr=", geneIDArray, "\" target=\"blank\">Studies</a>")



    plotSummary(countMatrix, genesCnt, symbolStrArray, organism_name, pathwaysLinkStr, rxnsLinkStr, metsLinkStr, studiesLinkStr, plotFile, viewType)
}

getGeneSummaryInfoTable <- function(orgStr, geneIDArray, geneSymArray, anatomy = "NA", disease = "NA", plotFile, viewType) {
    suppressPackageStartupMessages({
        library(tidyverse)
        library(httr)
        library(jsonlite)
    })
    
    currDir <- paste0("/", basename(getwd()))
    symbolStrArray <- as.vector(strsplit(geneSymArray, split = "__", fixed = TRUE)[[1]])
    geneArray <- as.vector(strsplit(geneIDArray, split = "__", fixed = TRUE)[[1]])
    
    # Determine organism name
    organism_name <- case_when(
        orgStr %in% c("Human", "human", "hsa", "Homo sapiens") ~ "Human",
        orgStr %in% c("Mouse", "mouse", "mmu", "Mus musculus") ~ "Mouse",
        orgStr %in% c("Rat", "rat", "rno", "Rattus norvegicus") ~ "Rat"
    )
    
    # Read RDS file
    rdsfilename <- paste0("./data/", orgStr, "_keggLink_mg.RDS")
    all_data <- readRDS(rdsfilename)
    
    # Process each gene
    countMatrix <- matrix(ncol = 4, nrow = length(geneArray))
    
    for (g in seq_along(geneArray)) {
        geneIdStr <- geneArray[g]
        geneSymbol <- symbolStrArray[g]
        org_entrzIdStr = paste0(orgStr, ":", geneIdStr)
        #print(org_entrzIdStr)
        # Get metabolites from RDS data
        metabolites <- all_data %>%
            filter(org_ezid == org_entrzIdStr, relation_type == "compound") %>%
            mutate(kegg_data = str_remove(kegg_data, "^cpd:")) %>%  # Remove "cpd:" prefix
            pull(kegg_data) %>%
            unique()

        #print(paste0("num metabolites = ", length(metabolites)))
        # Query Metabolomics Workbench for studies
        all_studies <- character(0)
        
        
        # Get counts
        genePthwyCnt <- all_data %>%
            filter(org_ezid == org_entrzIdStr, relation_type == "pathway") %>%
            n_distinct()
        
        geneRxnsCnt <- all_data %>%
            filter(org_ezid == org_entrzIdStr, relation_type == "reaction") %>%
            n_distinct()

            
        

        geneStudyCnt <- 0
        geneMetabCnt <- 0
        metabCnt <- 0
        allStudies <- c() # to collect all studies across metabolites

        if (length(metabolites) > 0) {
            for (m in 1:length(metabolites)) {
                metabStr <- metabolites[m]
                #print(metabStr)
                if (anatomy == "NA") {
                    anatomy <- ""
                }
                if (disease == "NA") {
                    disease <- ""
                }
                anatomyQryStr <- anatomy
                #print(anatomyQryStr)
                diseaseQryStr <- disease
                # https://metabolomicsworkbench.org/rest/metstat/;;;human;Fibroblast%20cells;;C00031 works 
                # but https://metabolomicsworkbench.org/rest/metstat/;;;human;Fibroblast+cells;;C00031 does not
                # PHP encodes space to + so we have to replace it by %20
                pat_str = "\\+"; rep_str = "%20";
                #if (!is_empty(anatomyId) && length(anatomyId) > 0 && str_detect(anatomyId, "\\+")) {
                #    anatomyQryStr <- str_replace_all(anatomyId, "\\+", "%20")
                if (!is_empty(anatomy) && length(anatomy) > 0 && str_detect(anatomy, pat_str)) {
                    anatomyQryStr <- str_replace_all(anatomy, pat_str, rep_str)
                }

                if (!is_empty(disease) && length(disease) > 0 && str_detect(disease, pat_str)) {
                    diseaseQryStr <- str_replace_all(disease, pat_str, rep_str)
                }
                path <- paste0("https://www.metabolomicsworkbench.org/rest/metstat/;;;", organism_name, ";", anatomyQryStr, ";", diseaseQryStr, ";", metabStr)
                #print(paste0("Path = ", path))

                jslist <- read_json(path, simplifyVector = TRUE)
                mydf_studies <- list_of_list_to_df(jslist)
                #print(head(mydf_studies))
                studiesVec <- mydf_studies$study
                refMetVec <- mydf_studies$refmet_name

                metabCnt <- metabCnt + length(unique(refMetVec))
                geneMetabCnt <- geneMetabCnt + length(unique(refMetVec))
                allStudies <- c(allStudies, studiesVec) # collect studies
            }
            #print(paste0("allStudies = ", allStudies))
            if (geneMetabCnt == 0) {
                geneMetabCnt <- length(metabolites)
            }
            geneStudyCnt <- length(unique(allStudies)) # count unique studies across all metabolites
        }
        #print(paste0("geneIdStr = ", geneIdStr, " genePthwyCnt = ", genePthwyCnt, " geneRxnsCnt = ", geneRxnsCnt, " geneMetabCnt = ", geneMetabCnt, " geneStudyCnt = ", geneStudyCnt))
        
        
        # Update count matrix
        countMatrix[g, ] <- c(genePthwyCnt, geneRxnsCnt, geneMetabCnt, geneStudyCnt)
    }
    
    # Generate links
    pathwaysLinkStr <- paste0("<a href=\"", currDir, "/pathways.php?species=", orgStr, 
                            "&GeneIDType=ENTREZID&anatomy=", anatomy, 
                            "&disease=", disease, "&GeneInfoStr=", geneIDArray, 
                            "\" target=\"_blank\">Pathways</a>")
    
    rxnsLinkStr <- paste0("<a href=\"", currDir, "/reactions.php?species=", orgStr,
                        "&GeneIDType=ENTREZID&anatomy=", anatomy,
                        "&disease=", disease, "&GeneInfoStr=", geneIDArray,
                        "\" target=\"_blank\">Reactions</a>")
    
    metsLinkStr <- paste0("<a href=\"", currDir, "/metabolites.php?species=", orgStr,
                        "&GeneIDType=ENTREZID&anatomy=", anatomy,
                        "&disease=", disease, "&GeneInfoStr=", geneIDArray,
                        "\" target=\"_blank\">Metabolites</a>")
    
    studiesLinkStr <- paste0("<a href=\"", currDir, "/studies.php?species=", orgStr,
                           "&GeneIDType=ENTREZID&anatomy=", anatomy,
                           "&disease=", disease, "&GeneInfoStr=", geneIDArray,
                           "\" target=\"_blank\">Studies</a>")
    
    # Call plotting function
    plotSummary(countMatrix, length(geneArray), symbolStrArray, organism_name,
                pathwaysLinkStr, rxnsLinkStr, metsLinkStr, studiesLinkStr,
                plotFile, viewType)
}

args <- commandArgs(trailingOnly = TRUE)

# Required arguments
if (length(args) < 5) {
    stop("Usage: Rscript extractMWGeneSummary.R <species> <geneIDArr> <geneSymArr> <filename> <viewType> [<anatomy>] [<disease>]")
}

species <- args[1]
geneIDArr <- args[2]
geneSymArr <- args[3]
filename <- args[4]
viewType <- args[5]

# Optional arguments
anatomy <- ifelse(length(args) >= 6, args[6], "")
disease <- ifelse(length(args) >= 7, args[7], "")
#print(paste0("anatomy = ", anatomy))
#print(paste0("disease = ", disease))
# tic("Total time elapsed = ");
if (preCompute == 1) {
    getGeneSummaryInfoTable(species, geneIDArr, geneSymArr, anatomy, disease, filename, viewType)
} else {
    getGeneSummaryInfoTableWithKeggQuery(species, geneIDArr, geneSymArr, anatomy, disease, filename, viewType)
}
# toc();

# Testing command example
# Rscript extraMWGeneSummary.R hsa 3098 HK1 foo.png json
