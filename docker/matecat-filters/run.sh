FILTER_BUILD=$(ls | grep -E "^filters-[0-9\.]+.jar$")
echo $FILTER_BUILD

java -cp ".:$FILTER_BUILD" com.matecat.converter.Main > ./logs & tail -f ./logs