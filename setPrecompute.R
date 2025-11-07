# MetGENE: susrinivasn@ucsd.edu; mano@sdsc.edu
# File to set the flag for precomputing
# This flag tells MetGENE to obtain KEGG related data from precomputed tables instead of KEGGREST API
# If set to 0, MetGNE uses keggLink and keggGet calls which slows down the fetch significantly
# In order to use precomputed tables, one must obtain KEGG license.

################################################
# Restrictions due to the use of KEGG APIs (https://www.kegg.jp/kegg/legal.html, see also https://www.pathway.jp/en/academic.html)
# If and only if the user has purchased license for KEGG FTP Data, they can activate a 'preCompute' mode to run faster version of MetGENE. To achieve this, please set preCompute = 1 below. Otherwise, please ensure that preCompute is set to 0. Further, to use the faster version, the user needs to run the R scripts in the 'data' folder first. Please see the respective R files in the 'data' folder for instructions to run them.
# Please see the files README.md and LICENSE for more details.
################################################

preCompute <- 0
