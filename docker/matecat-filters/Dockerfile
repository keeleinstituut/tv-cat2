FROM amazoncorretto:8

# RUN locale-gen en_US.UTF-8
ENV LANG='en_US.UTF-8' LANGUAGE='en_US:en' LC_ALL='en_US.UTF-8'
ENV APP_ROOT=/app

WORKDIR $APP_ROOT
COPY ./jar $APP_ROOT
COPY ./run.sh $APP_ROOT

RUN chmod u+x $APP_ROOT/run.sh

CMD $APP_ROOT/run.sh